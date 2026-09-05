@php($status = ($exception ?? null)?->getStatusCode() ?? 400)
@include('errors.layout', ['status' => $status, 'title' => 'Permintaan tidak dapat diproses', 'message' => 'Periksa kembali alamat atau data yang kamu kirimkan.'])
