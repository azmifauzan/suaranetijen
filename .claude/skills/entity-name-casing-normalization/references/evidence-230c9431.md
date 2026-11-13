$out = \\Illuminate\\Support\\Facades\\DB::transaction(function () use ($E, $new, $existing) {
...
$rules = []; foreach ($existing as $slug=>$p) { foreach ($p as $x) { $rules[] = [$slug,$x]; } } foreach ($new as $slug=>[$n,$p]) { foreach ($p as $x) { $rules[] = [$slug,$x]; } }
usort($rules, fn($a,$b)=>mb_strlen($b[1]) <=> mb_strlen($a[1]));
$plan = []; foreach ($E::query()->where('type','product')->whereNull('parent_id')->get(['id','name']) as $o) { $l = mb_strtolower($o->name).' '; foreach ($rules as [$slug,$x]) { if (str_starts_with($l,$x)) { $plan[$brandIds[$slug]][] = $o->id; break; } } }
echo 'PLAN='.json_encode($plan).PHP_EOL;
$n = 0; foreach ($plan as $bid=>$ids) { $n += $E::query()->whereIn('id',$ids)->whereNull('parent_id')->update(['parent_id'=>$bid]); }
$exp = array_sum(array_map('count',$plan)); if ($n !== $exp) { throw new \\RuntimeException("updated $n expected $exp"); }
RESULT={"created":{"iqoo":6104,"huawei":6105,"motorola":6106,"itel":6107,"oneplus":6108,"advan":6109,"nubia":6110},"updated":867,"orphans_left":771,"xiaomi_children":270}
FLUSHED
CF_PURGE success= True
MAP_DONE