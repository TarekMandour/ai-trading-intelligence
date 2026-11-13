<!doctype html>
<html lang="ar" dir="rtl">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>نتائج تحليل العملات الرقمية #{{ $analysisRun->id }}</title>
<style>
:root{font-family:Tahoma,Arial,sans-serif;color:#182230;background:#f4f6fa}*{box-sizing:border-box}body{margin:0;padding:22px;max-width:1500px;margin-inline:auto}a{color:#2448d8;text-decoration:none}.panel{background:#fff;border:1px solid #e4e8f0;border-radius:14px;padding:18px;margin:14px 0}.muted,.note{color:#667085}.tag{display:inline-block;padding:5px 10px;border-radius:30px;background:#eef2ff;font-size:12px;font-weight:bold}.good{background:#dcfce7;color:#166534}.wait{background:#fef3c7;color:#92400e}.bad{background:#fee2e2;color:#991b1b}.table-wrap{overflow-x:auto;background:#fff;border:1px solid #e4e8f0;border-radius:14px}.results{width:100%;border-collapse:collapse;min-width:850px}.results th,.results td{padding:13px 15px;text-align:right;border-bottom:1px solid #edf0f5;white-space:nowrap}.results th{background:#f8fafc;color:#475467;font-size:13px}.results tbody tr:hover{background:#fafbff}.coin-link{font-weight:bold;background:none;border:0;padding:0;color:#2448d8;font:inherit;cursor:pointer}.details-btn{border:1px solid #d0d5dd;background:#fff;border-radius:8px;padding:7px 11px;cursor:pointer;color:#344054}.details-btn:hover{background:#f2f4f7}.modal-backdrop{display:none;position:fixed;inset:0;background:#101828a8;z-index:1000;padding:24px;overflow-y:auto}.modal-backdrop.open{display:flex;align-items:flex-start;justify-content:center}.modal{width:min(100%,1050px);background:#fff;border-radius:16px;margin:auto;box-shadow:0 20px 60px #0003;overflow:hidden}.modal-head{position:sticky;top:0;background:#fff;border-bottom:1px solid #eaecf0;padding:16px 20px;display:flex;align-items:center;justify-content:space-between;gap:12px;z-index:2}.modal-head h2{font-size:19px;margin:0}.close{border:0;background:#f2f4f7;border-radius:9px;width:36px;height:36px;font-size:22px;cursor:pointer}.modal-body{padding:20px;line-height:1.8}.section{border:1px solid #eaecf0;border-radius:12px;padding:14px;margin:12px 0}.section h3,.section h4{margin:0 0 10px}.grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(135px,1fr));gap:9px}.metric{background:#f2f4f7;border-radius:9px;padding:10px}.metric small{display:block;color:#667085}.metric strong{display:block;font-size:17px;overflow-wrap:anywhere}.reason{background:#fff7ed;padding:8px 10px;border-radius:8px;margin:6px 0}.cols{display:grid;grid-template-columns:repeat(auto-fit,minmax(230px,1fr));gap:12px}pre{white-space:pre-wrap;overflow-wrap:anywhere;background:#101828;color:#e6edf7;padding:14px;border-radius:8px;max-height:350px;overflow:auto;direction:ltr;text-align:left}details{margin-top:10px}summary{cursor:pointer;font-weight:bold}.note{font-size:12px;line-height:1.7}@media(max-width:650px){body{padding:10px}.modal-backdrop{padding:8px}.modal-body{padding:12px}}
</style></head><body>
<p><a href="{{ route('analysis-runs.index') }}">← العودة إلى سجل عمليات التحليل</a></p>
<div class="panel">
<h1>نتائج تحليل العملات الرقمية #{{ $analysisRun->id }}</h1>
<p class="muted">تاريخ البدء: {{ $analysisRun->started_at?->format('Y-m-d H:i:s') ?? '—' }} · الانتهاء: {{ $analysisRun->completed_at?->format('Y-m-d H:i:s') ?? '—' }} · إصدار الإعدادات: {{ $analysisRun->configuration_version }}</p>
<span class="tag {{ $analysisRun->status === 'COMPLETED' ? 'good' : ($analysisRun->status === 'FAILED' ? 'bad' : 'wait') }}">{{ $analysisRun->status === 'COMPLETED' ? 'اكتمل التحليل' : ($analysisRun->status === 'FAILED' ? 'فشل التحليل' : ($analysisRun->status === 'RUNNING' ? 'جارٍ التحليل' : 'في قائمة الانتظار')) }}</span>
<span class="tag">مراجعة AI: {{ $analysisRun->ai_status ?? '—' }}</span>
@if($analysisRun->failure_reason)<p class="reason">تفاصيل الخطأ: {{ $analysisRun->failure_reason }}</p>@endif
<div class="grid">
<div class="metric"><small>عدد تقارير التحليل</small><strong>{{ $analysisRun->reports->count() }}</strong></div>
<div class="metric"><small>فرص دخول</small><strong>{{ $analysisRun->reports->where('status','ENTERABLE')->count() }}</strong></div>
<div class="metric"><small>انتظار</small><strong>{{ $analysisRun->reports->where('status','WAIT')->count() }}</strong></div>
<div class="metric"><small>لا توجد فرصة</small><strong>{{ $analysisRun->reports->where('status','NO_OPPORTUNITY')->count() }}</strong></div>
</div>
</div>
@php
$statusText=['ENTERABLE'=>'فرصة دخول مستوفية للشروط','WAIT'=>'انتظار التأكيد','NO_OPPORTUNITY'=>'لا توجد فرصة مناسبة','REJECTED'=>'تم استبعاد العملة بسبب البيانات','WATCHLIST'=>'مراقبة','FAILED'=>'فشل التحليل','INSUFFICIENT_DATA'=>'بيانات غير كافية'];
$profileText=['Scalping'=>'مضاربة سريعة (Scalping)','Intraday'=>'تداول يومي (Intraday)','Swing'=>'تداول متوسط (Swing)'];
$reasonText=['TIMEFRAME_ALIGNMENT_NOT_CONFIRMED'=>'الاتجاهات على الفريمات المختلفة غير متوافقة','STRATEGY_MULTI_TIMEFRAME_ALIGNMENT_CONFIRMED'=>'توافق الاتجاهات على الفريمات','ENTRY_GATES_NOT_FULLY_SATISFIED'=>'شروط الدخول لم تكتمل بالكامل','INSUFFICIENT_VALIDATED_CANDLE_EVIDENCE'=>'لا توجد أدلة كافية من الشموع','MARKET_ENTRY_DISTANCE_EXCEEDED_LIMIT_PLAN_RETAINED'=>'السعر ابتعد عن منطقة الدخول؛ تم الاحتفاظ بخطة الدخول المحددة','ORDER_BOOK_UNAVAILABLE'=>'تعذر الحصول على بيانات دفتر الأوامر','BINANCE_UNAVAILABLE'=>'تعذر الاتصال بمنصة Binance','INSUFFICIENT_CANDLE_HISTORY'=>'عدد الشموع غير كافٍ'];
$number=fn($v,$digits=8)=>is_numeric($v)?rtrim(rtrim(number_format((float)$v,$digits,'.',''),'0'),'.'):'—';
@endphp
<div class="table-wrap"><table class="results">
<thead><tr><th>العملة</th><th>الاستراتيجية</th><th>القرار</th><th>السيناريو</th><th>سعر الدخول</th><th>وقف الخسارة</th><th>الهدف الأول</th><th>التفاصيل</th></tr></thead><tbody>
@forelse($analysisRun->reports as $report)
@php
$status=$report->status??'UNKNOWN';$opportunity=$report->opportunity;$timeframes=$report->timeframes??[];$snapshot=$opportunity?->snapshot??[];$strategy=$timeframes['technical_strategy']??$snapshot['technical_strategy']??[];$candidate=$snapshot['candidate']??[];$costs=$timeframes['execution_costs']??$snapshot['execution_costs']??[];$ai=$opportunity?->aiReview;$badge=$status==='ENTERABLE'?'good':($status==='WAIT'?'wait':'bad');$modalId='crypto-report-'.$report->id;
@endphp
<tr>
<td><button type="button" class="coin-link" data-modal="{{ $modalId }}">{{ $report->symbol }}</button></td>
<td>{{ $profileText[$report->profile]??$report->profile }}</td>
<td><span class="tag {{ $badge }}">{{ $statusText[$status]??$status }}</span></td>
<td>{{ $opportunity?->scenario??'—' }}</td>
<td>{{ $number($opportunity?->entry) }}</td><td>{{ $number($opportunity?->stop) }}</td><td>{{ $number($opportunity?->target_1) }}</td>
<td><button type="button" class="details-btn" data-modal="{{ $modalId }}">عرض الشرح</button></td>
</tr>
<tr hidden><td colspan="8"><div id="{{ $modalId }}-content">
<div class="section"><h3>ملخص العملة وقرار المحرك</h3>
<p><span class="tag {{ $badge }}">{{ $statusText[$status]??$status }}</span> <span class="tag">{{ $profileText[$report->profile]??$report->profile }}</span></p>
@if($status==='ENTERABLE') اجتازت العملة شروط الاستراتيجية وقت الفحص. راجع السعر الحالي والسيولة وتكاليف التنفيذ قبل اتخاذ أي قرار.
@elseif($status==='WAIT') العملة تستحق المتابعة، لكن شروط الدخول أو جودة التنفيذ لم تكتمل. لا تطارد السعر خارج منطقة الدخول.
@elseif($status==='NO_OPPORTUNITY') لم تظهر فرصة مطابقة للشروط وقت الفحص؛ عدم الدخول قرار مقصود لتجنب صفقة ضعيفة.
@elseif($status==='REJECTED') لم يتمكن النظام من تقييم العملة بشكل موثوق بسبب مشكلة أو نقص في البيانات.
@else راجع تفاصيل التحليل والأسباب لمعرفة سبب هذه النتيجة.
@endif
</div>
<div class="section"><h3>تفاصيل الفرصة وخطة الصفقة</h3><p>السيناريو: <b>{{ $opportunity?->scenario??'لم يتم تحديد سيناريو مؤكد' }}</b></p>
<div class="grid">
<div class="metric"><small>الدخول</small><strong>{{ $number($opportunity?->entry) }}</strong></div>
<div class="metric"><small>وقف الخسارة</small><strong>{{ $number($opportunity?->stop) }}</strong></div>
<div class="metric"><small>الهدف الأول</small><strong>{{ $number($opportunity?->target_1) }}</strong></div>
<div class="metric"><small>الهدف الثاني</small><strong>{{ $number($opportunity?->target_2) }}</strong></div>
<div class="metric"><small>حالة الدخول السوقي</small><strong>{{ $costs['available']??false?'متاح':'غير متاح / غير محسوب' }}</strong></div>
<div class="metric"><small>صافي العائد للمخاطرة</small><strong>{{ $number($costs['net_risk_reward']??null,3) }}</strong></div>
</div>
@if(isset($costs['reason']))<p class="reason">{{ $reasonText[$costs['reason']]??str_replace('_',' ',$costs['reason']) }}</p>@endif
</div>
<div class="section"><h3>السياق والفريمات</h3>
@if(isset($timeframes['alignment']))<p>توافق الفريمات: <b>{{ ($timeframes['alignment']['allow_entry']??false)?'مسموح مبدئياً':'غير مؤكد' }}</b></p>@endif
@if(isset($timeframes['regime']))<p>حالة السوق: <b>{{ $timeframes['regime']['direction']??$timeframes['regime']['bias']??'غير محدد' }}</b> · السلوك: <b>{{ $timeframes['regime']['behavior']??'—' }}</b> · التذبذب: <b>{{ $timeframes['regime']['volatility']??'—' }}</b></p>@endif
@if(!empty($timeframes['states']))<h4>قراءة الفريمات</h4><div class="cols">@foreach($timeframes['states'] as $role=>$state)<div class="metric"><strong>{{ $role }}</strong><p>الاتجاه: {{ $state['direction']??'—' }}</p><p>السلوك: {{ $state['behavior']??'—' }}</p><p>التذبذب: {{ $state['volatility']??'—' }}</p></div>@endforeach</div>@endif
</div>
@if(!empty($report->reasons))<div class="section"><h3>أسباب القرار</h3>@foreach($report->reasons as $reason)<div class="reason">{{ $reasonText[$reason]??str_replace('_',' ',(string)$reason) }}</div>@endforeach</div>@endif
@if(!empty($report->data_quality_warnings))<div class="section"><h3>تحذيرات جودة البيانات</h3>@foreach($report->data_quality_warnings as $warning)<div class="reason">{{ $warning }}</div>@endforeach</div>@endif
@if(!empty($costs))<div class="section"><h3>تكاليف التنفيذ والسيولة</h3><div class="grid">
<div class="metric"><small>الرسوم</small><strong>{{ $number($costs['fee']??null) }}</strong></div><div class="metric"><small>فرق السعر Spread</small><strong>{{ $number($costs['spread']??null) }}</strong></div><div class="metric"><small>الانزلاق Slippage</small><strong>{{ $number($costs['slippage']??null) }}</strong></div><div class="metric"><small>VWAP الشراء</small><strong>{{ $number($costs['buy_vwap']??null) }}</strong></div><div class="metric"><small>VWAP البيع</small><strong>{{ $number($costs['sell_vwap']??null) }}</strong></div><div class="metric"><small>التكلفة الإجمالية</small><strong>{{ $number($costs['total']??null) }}</strong></div>
</div><p class="note">بيانات دفتر الأوامر لقطة وقت الفحص وليست ضماناً للسيولة أو تنفيذ الصفقة.</p></div>@endif
@if($ai)<div class="section"><h3>مراجعة الذكاء الاصطناعي</h3><p>الحالة: <b>{{ $ai->status }}</b> · المزود: {{ $ai->provider??'—' }} · النموذج: {{ $ai->model??'—' }}</p>
@if($ai->review)<p>التقييم: <b>{{ $ai->review['assessment']??'—' }}</b> · قوة الأدلة: {{ $ai->review['confidence_label']??'—' }} · توافق الفريمات: {{ $ai->review['timeframe_alignment']??'—' }}</p><p>التفسير: {{ $ai->review['rationale']??'—' }}</p>
@if(!empty($ai->review['missing_confirmations']))<h4>التأكيدات الناقصة</h4><ul>@foreach($ai->review['missing_confirmations'] as $item)<li>{{ $item }}</li>@endforeach</ul>@endif
@if(!empty($ai->review['risk_observations']))<h4>ملاحظات المخاطر</h4><ul>@foreach($ai->review['risk_observations'] as $item)<li>{{ $item }}</li>@endforeach</ul>@endif
@endif
<p class="note">مراجعة AI استشارية فقط؛ قرار Laravel الحتمي ومستويات الصفقة هما المرجع، ولا يستطيع AI اعتماد فرصة مرفوضة أو تغيير الأسعار.</p>
</div>@endif
<div class="section"><h3>تفاصيل التحليل الفني الكاملة</h3>
@if(!empty($strategy))<details><summary>الاستراتيجية الفنية</summary><pre>{{ json_encode($strategy,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) }}</pre></details>@endif
@if(!empty($timeframes))<details><summary>بيانات الفريمات والسياق</summary><pre>{{ json_encode($timeframes,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) }}</pre></details>@endif
@if(!empty($snapshot))<details><summary>لقطة الفرصة والأدلة المحفوظة</summary><pre>{{ json_encode($snapshot,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) }}</pre></details>@endif
@if($opportunity)<details><summary>الأدلة التفصيلية المسجلة</summary><pre>{{ json_encode($opportunity->evidence->map(fn($item)=>['kind'=>$item->kind,'payload'=>$item->payload]),JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) }}</pre></details>@endif
</div>
</div></td></tr>
@empty
<tr><td colspan="8" class="muted">لا توجد تقارير عملات محفوظة في عملية التحليل هذه.</td></tr>
@endforelse
</tbody></table></div>
<p class="note">هذا التقرير خاص بتحليل Binance Spot الفوري Long-only. لا يعني قرار الدخول ضمان الربح، وبيانات دفتر الأوامر لقطة لحظية. لا يتم تنفيذ أوامر تداول تلقائياً.</p>
<div class="modal-backdrop" id="detailModal" role="dialog" aria-modal="true" aria-labelledby="modalTitle">
<div class="modal"><div class="modal-head"><h2 id="modalTitle">شرح نتيجة العملة</h2><button class="close" type="button" id="closeModal" aria-label="إغلاق">×</button></div><div class="modal-body" id="modalBody"></div></div></div>
<script>
(function(){
 const modal=document.getElementById('detailModal'),body=document.getElementById('modalBody'),title=document.getElementById('modalTitle');
 function close(){modal.classList.remove('open');body.replaceChildren();document.body.style.overflow='';}
 document.querySelectorAll('[data-modal]').forEach(function(button){button.addEventListener('click',function(){
  const source=document.getElementById(button.dataset.modal+'-content');if(!source)return;
  const row=button.closest('tr'),cells=row?row.querySelectorAll('td'):[];
  title.textContent=(cells[0]?.innerText||'تفاصيل العملة')+' — '+(cells[1]?.innerText||'');
  body.innerHTML=source.innerHTML;modal.classList.add('open');document.body.style.overflow='hidden';
 });});
 document.getElementById('closeModal').addEventListener('click',close);
 modal.addEventListener('click',function(event){if(event.target===modal)close();});
 document.addEventListener('keydown',function(event){if(event.key==='Escape'&&modal.classList.contains('open'))close();});
})();
</script>
</body></html>