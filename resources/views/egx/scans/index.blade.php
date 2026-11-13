<!doctype html>
<html lang="ar" dir="rtl">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>سجل نتائج فحص EGX</title>
<style>
:root{font-family:Tahoma,Arial,sans-serif;color:#182230;background:#f4f6fa}*{box-sizing:border-box}body{margin:0;padding:28px;max-width:1250px;margin-inline:auto}a{color:#2448d8;text-decoration:none}.head,.panel,.run{background:#fff;border:1px solid #e4e8f0;border-radius:14px;padding:18px;margin:14px 0}.muted{color:#667085}.run{display:flex;justify-content:space-between;gap:16px;align-items:center;flex-wrap:wrap}.tag{display:inline-block;padding:5px 10px;border-radius:30px;background:#eef2ff;font-size:13px}.good{background:#dcfce7;color:#166534}.wait{background:#fef3c7;color:#92400e}.bad{background:#fee2e2;color:#991b1b}h1{margin:0 0 8px}h3{margin:0 0 7px}@media(max-width:650px){body{padding:12px}}
</style></head><body>
<div class="head"><a href="{{ url('/') }}">الرئيسية</a><h1>سجل عمليات الفحص السابقة</h1><p class="muted">اختر عملية فحص لعرض الأسهم التي تم تحليلها وشرح حالة كل سهم على حدة.</p></div>
@forelse($runs as $run)
<div class="run">
<div><h3>فحص #{{ $run->id }} — {{ $run->watchlist->name ?? 'قائمة محذوفة' }}</h3>
<p class="muted">التاريخ: {{ $run->started_at?->format('Y-m-d H:i') ?? $run->created_at?->format('Y-m-d H:i') }} · الأسهم: {{ count($run->results ?? []) }} · الفريمات: {{ implode('، ', $run->profiles ?? []) }}</p></div>
<div><span class="tag {{ $run->status === 'COMPLETED' ? 'good' : ($run->status === 'FAILED' ? 'bad' : 'wait') }}">{{ $run->status === 'COMPLETED' ? 'مكتمل' : ($run->status === 'FAILED' ? 'فشل' : 'قيد التنفيذ') }}</span>
<a href="{{ route('egx.scans.show',$run) }}">عرض التفاصيل ←</a></div>
</div>
@empty
<div class="panel">لا توجد عمليات فحص محفوظة حتى الآن.</div>
@endforelse
{{ $runs->links() }}
</body></html>