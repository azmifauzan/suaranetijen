import importlib.util
import json
import os
from pathlib import Path
import subprocess
import tempfile
import unittest
from unittest.mock import Mock, patch

spec = importlib.util.spec_from_file_location('codex_runner', Path(__file__).parents[1] / 'examples/codex_runner.py')
runner = importlib.util.module_from_spec(spec)
spec.loader.exec_module(runner)
BODY = {'messages': [{'role': 'user', 'content': 'synthetic opinion'}],
        'json_schema': {'name': 'test', 'schema': {'type': 'object'}}}


class CodexRunnerTest(unittest.TestCase):
    def error(self, action, status, code):
        with self.assertRaises(runner.RunnerError) as caught:
            action()
        self.assertEqual((caught.exception.status, caught.exception.code), (status, code))
        return caught.exception

    def test_http_auth_rejects_requests_before_inference(self):
        instance = runner.Runner('x' * 32)
        handler = object.__new__(runner.handler_for(instance))
        handler.connection = Mock()
        handler.headers = {}
        handler.reply = Mock()
        with patch.object(instance, 'chat') as chat:
            handler.dispatch()
            chat.assert_not_called()
        handler.reply.assert_called_once_with(401, {'error': 'unauthorized'})

    def test_http_preserves_retry_after_without_exposing_details(self):
        instance = runner.Runner('x' * 32)
        handler = object.__new__(runner.handler_for(instance))
        handler.connection = Mock()
        encoded = json.dumps(BODY).encode()
        handler.headers = {'Authorization': 'Bearer ' + 'x' * 32, 'Content-Length': str(len(encoded))}
        handler.command, handler.path = 'POST', '/chat'
        handler.rfile = __import__('io').BytesIO(encoded)
        handler.reply = Mock()
        with patch.object(instance, 'chat', side_effect=runner.RunnerError(429, 'codex_quota_limited', 300)):
            handler.dispatch()
        handler.reply.assert_called_once_with(429, {'error': 'codex_quota_limited'}, 300)

    def test_requested_timeout_bounds_the_cli_process(self):
        with patch.dict(os.environ, {'CODEX_HOME': '/private/auth'}), \
                patch.object(runner.subprocess, 'run', side_effect=subprocess.TimeoutExpired('codex', 45)) as execute:
            self.error(lambda: runner.infer(*runner.validate_request({**BODY, 'timeout_seconds': 45})), 504, 'inference_timeout')
            self.assertEqual(execute.call_args.kwargs['timeout'], 45)
        for value in (0, 121, True, "45"):
            with self.subTest(value=value):
                self.error(lambda: runner.validate_request({**BODY, 'timeout_seconds': value}), 422, 'invalid_timeout')

    def test_invalid_requests_never_start_cli(self):
        for value in [None, {}, {**BODY, 'messages': [{'role': 'tool', 'content': 'x'}]},
                      {**BODY, 'json_schema': {'schema': {'type': 'array'}}}]:
            with self.subTest(value=value), patch.object(runner, 'infer') as infer:
                self.error(lambda: runner.Runner('x' * 32).chat(value), 422, 'invalid_request')
                infer.assert_not_called()

    def test_concurrent_requests_and_rate_limits_are_released(self):
        instance = runner.Runner('x' * 32, per_minute=1)
        instance.lock.acquire()
        self.error(lambda: instance.chat(BODY), 429, 'runner_busy')
        instance.lock.release()
        with patch.object(runner, 'infer', return_value={'ok': True}) as infer:
            self.assertEqual(instance.chat(BODY), {'ok': True})
            self.error(lambda: instance.chat(BODY), 429, 'runner_rate_limited')
            self.assertEqual(infer.call_count, 1)
        self.assertEqual(instance.completed, 1)

    def test_cli_has_no_app_environment_and_requires_structured_output(self):
        def execute(command, **kwargs):
            self.assertNotIn('APP_KEY', kwargs['env'])
            self.assertNotIn('CODEX_RUNNER_TOKEN', kwargs['env'])
            self.assertEqual(kwargs['env']['CODEX_HOME'], '/private/auth')
            self.assertIn('--ephemeral', command)
            self.assertIn('--ignore-user-config', command)
            for feature in ('shell_tool', 'multi_agent', 'apps', 'plugins', 'hooks', 'remote_plugin'):
                self.assertIn(feature, command)
            self.assertEqual(kwargs['timeout'], 120)
            Path(command[command.index('--output-last-message') + 1]).write_text('{"ok":true}')
            self.assertEqual(json.loads(Path(command[command.index('--output-schema') + 1]).read_text()), {'type': 'object'})
            return subprocess.CompletedProcess(command, 0, '', '')
        with patch.dict(os.environ, {'CODEX_HOME': '/private/auth', 'APP_KEY': 'secret', 'CODEX_RUNNER_TOKEN': 'secret'}), \
                patch.object(runner.subprocess, 'run', side_effect=execute):
            self.assertEqual(runner.infer(*runner.validate_request(BODY)), {'ok': True})

    def test_quota_auth_and_unexpected_errors_do_not_expose_cli_output(self):
        for stderr, status, code in [('usage limit reached SECRET', 429, 'codex_quota_limited'),
                                     ('401 refresh_token SECRET', 401, 'codex_login_required'),
                                     ('failure SECRET', 502, 'codex_inference_failed')]:
            with self.subTest(code=code), patch.dict(os.environ, {'CODEX_HOME': '/private/auth'}), \
                    patch.object(runner.subprocess, 'run', return_value=subprocess.CompletedProcess([], 1, '', stderr)):
                error = self.error(lambda: runner.infer(*runner.validate_request(BODY)), status, code)
                self.assertNotIn('SECRET', str(error))

    def test_timeout_and_invalid_json_are_failures(self):
        with patch.dict(os.environ, {'CODEX_HOME': '/private/auth'}), \
                patch.object(runner.subprocess, 'run', side_effect=subprocess.TimeoutExpired('codex', 120)):
            self.error(lambda: runner.infer(*runner.validate_request(BODY)), 504, 'inference_timeout')
        for text in ['broken', '[]', 'null']:
            def execute(command, **_kwargs):
                Path(command[command.index('--output-last-message') + 1]).write_text(text)
                return subprocess.CompletedProcess(command, 0, '', '')
            with self.subTest(text=text), patch.dict(os.environ, {'CODEX_HOME': '/private/auth'}), \
                    patch.object(runner.subprocess, 'run', side_effect=execute):
                self.error(lambda: runner.infer(*runner.validate_request(BODY)), 502, 'invalid_codex_json')

    def test_failure_releases_slot_for_next_call(self):
        instance = runner.Runner('x' * 32)
        with patch.object(runner, 'infer', side_effect=runner.RunnerError(504, 'inference_timeout')):
            self.error(lambda: instance.chat(BODY), 504, 'inference_timeout')
        with patch.object(runner, 'infer', return_value={}):
            self.assertEqual(instance.chat(BODY), {})
        self.assertEqual((instance.failed, instance.completed), (1, 1))


if __name__ == '__main__':
    unittest.main()
