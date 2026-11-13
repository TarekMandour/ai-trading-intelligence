<!doctype html>
<html lang="ar" dir="rtl">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>تفاصيل فحص EGX #{{ $scanRun->id }}</title>
<style>
:root{font-family:Tahoma,Arial,sans-serif;color:#182230;background:#f4f6fa}*{box-sizing:border-box}body{margin:0;padding:22px;max-width:1500px;margin-inline:auto}a{color:#2448d8;text-decoration:none}.panel{background:#fff;border:1px solid #e4e8f0;border-radius:14px;padding:18px;margin:14px 0}.muted,.note{color:#667085}.tag{display:inline-block;padding:5px 10px;border-radius:30px;background:#eef2ff;font-size:12px;font-weight:bold}.good{background:#dcfce7;color:#166534}.wait{background:#fef3c7;color:#92400e}.bad{background:#fee2e2;color:#991b1b}.table-wrap{overflow-x:auto;background:#fff;border:1px solid #e4e8f0;border-radius:14px}.results{width:100%;border-collapse:collapse;min-width:760px}.results th,.results td{padding:13px 15px;text-align:right;border-bottom:1px solid #edf0f5;white-space:nowrap}.results th{background:#f8fafc;color:#475467;font-size:13px}.results tbody tr:hover{background:#fafbff}.stock-link{font-weight:bold;background:none;border:0;padding:0;color:#2448d8;font:inherit;cursor:pointer}.details-btn{border:1px solid #d0d5dd;background:#fff;border-radius:8px;padding:7px 11px;cursor:pointer;color:#344054}.details-btn:hover{background:#f2f4f7}.modal-backdrop{display:none;position:fixed;inset:0;background:#101828a8;z-index:1000;padding:24px;overflow-y:auto}.modal-backdrop.open{display:flex;align-items:flex-start;justify-content:center}.modal{width:min(100%,1000px);background:#fff;border-radius:16px;margin:auto;box-shadow:0 20px 60px #0003;overflow:hidden}.modal-head{position:sticky;top:0;background:#fff;border-bottom:1px solid #eaecf0;padding:16px 20px;display:flex;align-items:center;justify-content:space-between;gap:12px;z-index:2}.modal-head h2{font-size:19px;margin:0}.close{border:0;background:#f2f4f7;border-radius:9px;width:36px;height:36px;font-size:22px;cursor:pointer}.modal-body{padding:20px;line-height:1.8}.section{border:1px solid #eaecf0;border-radius:12px;padding:14px;margin:12px 0}.section h3,.section h4{margin:0 0 10px}.grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(135px,1fr));gap:9px}.metric{background:#f2f4f7;border-radius:9px;padding:10px}.metric small{display:block;color:#667085}.metric strong{display:block;font-size:17px;overflow-wrap:anywhere}.reason{background:#fff7ed;padding:8px 10px;border-radius:8px;margin:6px 0}.cols{display:grid;grid-template-columns:repeat(auto-fit,minmax(230px,1fr));gap:12px}pre{white-space:pre-wrap;overflow-wrap:anywhere;background:#101828;color:#e6edf7;padding:14px;border-radius:8px;max-height:350px;overflow:auto;direction:ltr;text-align:left}.note{font-size:12px;line-height:1.7}@media(max-width:650px){body{padding:10px}.modal-backdrop{padding:8px}.modal-body{padding:12px}}
</style></head><body>
<p><a href="{{ route('egx.scans.index') }}">← العودة إلى سجل الفحوصات</a> · <a href="{{ url('/') }}">الرئيسية</a></p>
<div class="panel">
<h1>نتائج عملية الفحص #{{ $scanRun->id }}</h1>
<p class="muted">قائمة الأسهم: {{ $scanRun->watchlist->name ?? 'غير متاحة' }} · البدء: {{ $scanRun->started_at?->format('Y-m-d H:i:s') ?? '—' }} · الانتهاء: {{ $scanRun->completed_at?->format('Y-m-d H:i:s') ?? '—' }}</p>
<span class="tag {{ $scanRun->status === 'COMPLETED' ? 'good' : ($scanRun->status === 'FAILED' ? 'bad' : 'wait') }}">{{ $scanRun->status === 'COMPLETED' ? 'اكتمل الفحص' : ($scanRun->status === 'FAILED' ? 'فشل الفحص' : 'الفحص قيد التنفيذ') }}</span>
<span class="tag">مراجعة Gemini: {{ $scanRun->ai_status ?? 'غير متاحة' }}</span>
@if($scanRun->failure_reason)<p class="reason">{{ $scanRun->failure_reason }}</p>@endif
</div>
@php
$statusText=['ENTERABLE'=>'فرصة دخول مستوفية للشروط','WAIT'=>'انتظار التأكيد','WATCHLIST'=>'مراقبة السهم','NO_OPPORTUNITY'=>'لا توجد فرصة مناسبة','REJECTED'=>'تم استبعاد السهم بسبب البيانات','REJECT'=>'مرفوض','INSUFFICIENT_DATA'=>'بيانات غير كافية'];
$reasonText=['DOWNTREND'=>'الاتجاه العام هابط','MULTI_TIMEFRAME_TREND_NOT_ALIGNED'=>'الاتجاهات على الفريمات المختلفة غير متوافقة','ENTRY_CONFIRMATION_PENDING'=>'ننتظر تأكيد الدخول','LOW_RISK_REWARD'=>'العائد المتوقع لا يبرر المخاطرة','SUPPORT_BOUNCE_CONFIRMED'=>'ظهرت إشارة ارتداد من الدعم','ENTRY_TIMEFRAME_BREAKOUT_CONFIRMED'=>'تم تأكيد اختراق على فريم الدخول','INSUFFICIENT_CANDLE_HISTORY'=>'عدد الشموع غير كافٍ','MISSING_CONTEXT_CANDLES'=>'بيانات فريم الاتجاه غير متاحة'];
$trendText=['STRONG_UP'=>'صاعد قوي','UP'=>'صاعد','SIDEWAYS'=>'عرضي','DOWN'=>'هابط','UNKNOWN'=>'غير محدد','STRONG_DOWN'=>'هابط قوي','BULLISH'=>'إيجابي','BEARISH'=>'سلبي','UNCERTAIN'=>'غير واضح'];
$number=fn($v,$digits=3)=>is_numeric($v)?number_format((float)$v,$digits):'—';
@endphp
@php $rowCount=0; @endphp
<div class="table-wrap">
<table class="results">
<thead><tr><th>السهم</th><th>الرمز</th><th>الاستراتيجية</th><th>النتيجة</th><th>درجة الفرصة</th><th>التفاصيل</th></tr></thead>
<tbody>
@forelse($scanRun->results ?? [] as $stock)
@foreach(($stock['profiles'] ?? []) as $profile=>$result)
@php
$rowCount++;
$status=$result['status']??$result['decision']??'UNKNOWN';
$metrics=$result['metrics']??[];$candidate=$result['candidate']??[];$align=$result['timeframe_alignment']??[];$analysis=$result['timeframe_analyses']??[];$ai=$result['gemini_analysis']??[];$reasons=$result['reasons']??[];
$context=$align['context']??$analysis['context']['trend']??'UNKNOWN';$structure=$align['structure']??$analysis['structure']['trend']??'UNKNOWN';$setup=$align['setup']??$analysis['setup']['trend']??'UNKNOWN';
$badge=$status==='ENTERABLE'?'good':(in_array($status,['WAIT','WATCHLIST'])?'wait':'bad');
$modalId='scan-detail-'.$rowCount;
@endphp
<tr>
<td><button type="button" class="stock-link" data-modal="{{ $modalId }}">{{ $stock['arabic_name']??$stock['name']??'سهم' }}</button></td>
<td>{{ $stock['symbol']??'—' }}</td>
<td>{{ $profile==='Scalping'?'مضاربة سريعة':($profile==='Intraday'?'تداول يومي':'تداول متوسط') }}</td>
<td><span class="tag {{ $badge }}">{{ $statusText[$status]??$status }}</span></td>
<td>{{ $number($metrics['opportunity_score']??null,1) }} / 100</td>
<td><button type="button" class="details-btn" data-modal="{{ $modalId }}">عرض الشرح</button></td>
</tr>
<tr hidden><td colspan="6"><div id="{{ $modalId }}-content">
<div class="section">
<h3>الخلاصة وقرار المحرك</h3>
<p><span class="tag {{ $badge }}">{{ $statusText[$status]??$status }}</span> <span class="tag">درجة الفرصة: {{ $number($metrics['opportunity_score']??null,1) }}/100 · {{ $metrics['opportunity_grade']??'—' }}</span></p>
@if($status==='ENTERABLE') اجتاز السهم شروط الاستراتيجية وقت الفحص، مع ضرورة مراجعة السعر الحالي قبل التنفيذ.
@elseif(in_array($status,['WAIT','WATCHLIST'])) السهم يستحق المتابعة، لكن شروط الدخول لم تكتمل بعد. لا تطارد السعر خارج منطقة الدخول.
@elseif($status==='NO_OPPORTUNITY') لا توجد فرصة مطابقة للشروط وقت الفحص؛ عدم الدخول قرار مقصود لتجنب صفقة ضعيفة.
@elseif($status==='REJECTED') لم يتمكن النظام من تقييم السهم بشكل موثوق بسبب مشكلة أو نقص في البيانات.
@else النتيجة تحتاج مراجعة التفاصيل والأسباب أدناه.
@endif
</div>
<div class="section"><h3>اتجاه الفريمات والتوافق</h3><div class="cols">
<div>الاتجاه الأكبر: <b>{{ $trendText[$context]??$context }}</b><br>الهيكل السعري: <b>{{ $trendText[$structure]??$structure }}</b><br>فريم الإعداد: <b>{{ $trendText[$setup]??$setup }}</b><br>التوافق: <b>{{ ($align['aligned']??false)?'متوافقة':'غير متوافقة' }}</b></div>
<div>قوة الاتجاه: <b>{{ $number($metrics['trend_score']??null,1) }}/100</b><br>قوة الحجم: <b>{{ $number($metrics['volume_score']??null,1) }}/100</b><br>الحجم النسبي RVOL: <b>{{ $number($metrics['relative_volume']??null,2) }}×</b><br>سرعة الحركة: <b>{{ $number($metrics['speed_score']??null,1) }}/100</b></div>
</div></div>
@if(count($reasons))<div class="section"><h3>لماذا ظهرت هذه النتيجة؟</h3>@foreach($reasons as $reason)<div class="reason">{{ $reasonText[$reason]??str_replace('_',' ',(string)$reason) }}</div>@endforeach</div>@endif
@if(!empty($candidate))<div class="section"><h3>خطة الصفقة المقترحة</h3><div class="grid">
<div class="metric"><small>سعر الدخول</small><strong>{{ $number($candidate['entry']??null) }}</strong></div>
<div class="metric"><small>منطقة الدخول</small><strong>{{ $number($candidate['entry_low']??null) }} – {{ $number($candidate['entry_high']??null) }}</strong></div>
<div class="metric"><small>وقف الخسارة</small><strong>{{ $number($candidate['stop']??null) }}</strong></div>
<div class="metric"><small>الهدف الأول</small><strong>{{ $number($candidate['target1']??null) }}</strong></div>
<div class="metric"><small>الهدف الثاني</small><strong>{{ $number($candidate['target2']??null) }}</strong></div>
<div class="metric"><small>العائد إلى المخاطرة</small><strong>{{ $number($candidate['risk_reward']??null,2) }}</strong></div>
</div><p class="note">المستويات محسوبة من بيانات وقت الفحص وليست ضماناً للتنفيذ أو الربح. تحقق من السعر الحالي قبل أي قرار.</p></div>@endif
@if(!empty($result['levels']))<div class="section"><h3>الدعم والمقاومة</h3><div class="cols"><div><h4>الدعم</h4>@forelse($result['levels']['supports']??[] as $level)<p>{{ $number($level['price']??null) }} · القوة {{ $number($level['strength']??null,1) }}</p>@empty<p>لا توجد مستويات دعم موثقة.</p>@endforelse</div><div><h4>المقاومة</h4>@forelse($result['levels']['resistances']??[] as $level)<p>{{ $number($level['price']??null) }} · القوة {{ $number($level['strength']??null,1) }}</p>@empty<p>لا توجد مستويات مقاومة موثقة.</p>@endforelse</div></div></div>@endif
@if(!empty($ai))<div class="section"><h3>مراجعة Gemini المستقلة — {{ $ai['status']??'غير معروف' }}</h3>
@if(isset($ai['summary']))<p>{{ $ai['summary'] }}</p>@endif
@if(isset($ai['decision']))<p>قرار Gemini: {{ $statusText[$ai['decision']]??$ai['decision'] }} · الثقة: {{ $ai['confidence']??'—' }}</p>@endif
@if(!empty($ai['key_evidence']))<h4>الأدلة</h4><ul>@foreach($ai['key_evidence'] as $item)<li>{{ $item }}</li>@endforeach</ul>@endif
@if(!empty($ai['risks']))<h4>المخاطر</h4><ul>@foreach($ai['risks'] as $item)<li>{{ $item }}</li>@endforeach</ul>@endif
@if(isset($ai['error']))<p class="reason">تعذر إكمال المراجعة: {{ $ai['error'] }}</p>@endif
<details><summary>بيانات مراجعة Gemini الخام</summary><pre>{{ json_encode($ai,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) }}</pre></details>
</div>@endif
<div class="section"><h3>البيانات الفنية الكاملة</h3><details><summary>عرض بيانات التحليل الخام</summary><pre>{{ json_encode($result,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) }}</pre></details></div>
</div></td></tr>
@endforeach
@empty
<tr><td colspan="6" class="muted">لا توجد نتائج أسهم محفوظة في عملية الفحص هذه.</td></tr>
@endforelse
@if($rowCount===0 && !empty($scanRun->results))<tr><td colspan="6">لا توجد نتائج مرتبطة باستراتيجيات.</td></tr>@endif
</tbody></table></div>
<p class="note">درجة الفرصة ليست احتمالاً إحصائياً للربح. النتائج أداة بحث وتحليل وليست توصية استثمارية أو ضماناً للصفقات.</p>
<div class="modal-backdrop" id="detailModal" role="dialog" aria-modal="true" aria-labelledby="modalTitle">
<div class="modal"><div class="modal-head"><h2 id="modalTitle">شرح نتيجة السهم</h2><button class="close" type="button" id="closeModal" aria-label="إغلاق">×</button></div><div class="modal-body" id="modalBody"></div></div></div>
<script>
(function(){
 const modal=document.getElementById('detailModal'), body=document.getElementById('modalBody'), title=document.getElementById('modalTitle');
 function close(){modal.classList.remove('open');body.replaceChildren();document.body.style.overflow='';}
 document.querySelectorAll('[data-modal]').forEach(function(button){
  button.addEventListener('click',function(){
   const source=document.getElementById(button.dataset.modal+'-content');
   if(!source)return;
   const row=button.closest('tr');
   const cells=row?row.querySelectorAll('td'):[];
   title.textContent=(cells[0]?.innerText||'تفاصيل السهم')+' — '+(cells[2]?.innerText||'');
   body.innerHTML=source.innerHTML;modal.classList.add('open');document.body.style.overflow='hidden';
  });
 });
 document.getElementById('closeModal').addEventListener('click',close);
 modal.addEventListener('click',function(event){if(event.target===modal)close();});
 document.addEventListener('keydown',function(event){if(event.key==='Escape'&&modal.classList.contains('open'))close();});
})();
</script>
</body></html>