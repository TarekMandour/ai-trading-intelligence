<!doctype html>
<html lang="ar" dir="rtl">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>تفاصيل فحص EGX #{{ $scanRun->id }}</title>
<style>
:root{font-family:Tahoma,Arial,sans-serif;color:#182230;background:#f4f6fa}*{box-sizing:border-box}body{margin:0;padding:24px;max-width:1450px;margin-inline:auto}a{color:#2448d8;text-decoration:none}.panel,.stock,.profile{background:#fff;border:1px solid #e4e8f0;border-radius:14px;padding:18px;margin:14px 0}.stock{border-right:5px solid #64748b}.profile{background:#fbfcfe}.muted{color:#667085}.tag{display:inline-block;padding:6px 11px;border-radius:30px;background:#eef2ff;font-size:13px;font-weight:bold}.good{background:#dcfce7;color:#166534}.wait{background:#fef3c7;color:#92400e}.bad{background:#fee2e2;color:#991b1b}.grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(145px,1fr));gap:10px;margin:14px 0}.metric{background:#f1f4f9;border-radius:10px;padding:12px}.metric small{display:block;color:#667085}.metric strong{display:block;font-size:18px;margin-top:5px;overflow-wrap:anywhere}.cols{display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:12px}.explain{background:#f8fafc;border:1px solid #e7ebf1;padding:12px;border-radius:10px;line-height:1.8}.reason{padding:8px 10px;background:#fff7ed;border-radius:8px;margin:6px 0}details{margin-top:12px}summary{cursor:pointer;font-weight:bold}pre{white-space:pre-wrap;overflow-wrap:anywhere;background:#101828;color:#e6edf7;padding:14px;border-radius:8px;max-height:480px;overflow:auto;direction:ltr;text-align:left}.profile h3{margin-top:0}.note{color:#667085;font-size:13px;line-height:1.7}.bar{height:9px;background:#e5e7eb;border-radius:10px;overflow:hidden}.bar span{display:block;height:100%;background:#64748b}@media(max-width:650px){body{padding:10px}}
</style></head><body>
<p><a href="{{ route('egx.scans.index') }}">← العودة إلى سجل الفحوصات</a> · <a href="{{ url('/') }}">الرئيسية</a></p>
<div class="panel">
<h1>تفاصيل عملية الفحص #{{ $scanRun->id }}</h1>
<p class="muted">قائمة الأسهم: {{ $scanRun->watchlist->name ?? 'غير متاحة' }} · تاريخ البدء: {{ $scanRun->started_at?->format('Y-m-d H:i:s') ?? '—' }} · الانتهاء: {{ $scanRun->completed_at?->format('Y-m-d H:i:s') ?? '—' }}</p>
<span class="tag {{ $scanRun->status === 'COMPLETED' ? 'good' : ($scanRun->status === 'FAILED' ? 'bad' : 'wait') }}">{{ $scanRun->status === 'COMPLETED' ? 'اكتمل الفحص' : ($scanRun->status === 'FAILED' ? 'فشل الفحص' : 'الفحص قيد التنفيذ') }}</span>
<span class="tag">مراجعة Gemini: {{ $scanRun->ai_status ?? 'غير متاحة' }}</span>
@if($scanRun->failure_reason)<p class="reason">{{ $scanRun->failure_reason }}</p>@endif
</div>
@php
$statusText = ['ENTERABLE'=>'فرصة دخول مؤكدة بالشروط الحالية','WAIT'=>'انتظار التأكيد','WATCHLIST'=>'مراقبة السهم','NO_OPPORTUNITY'=>'لا توجد فرصة مناسبة','REJECTED'=>'تم استبعاد السهم بسبب البيانات','REJECT'=>'مرفوض','INSUFFICIENT_DATA'=>'بيانات غير كافية'];
$reasonText = ['DOWNTREND'=>'الاتجاه العام هابط','MULTI_TIMEFRAME_TREND_NOT_ALIGNED'=>'الاتجاهات على الفريمات المختلفة غير متوافقة','ENTRY_CONFIRMATION_PENDING'=>'ننتظر تأكيد الدخول','LOW_RISK_REWARD'=>'العائد المتوقع لا يبرر المخاطرة','SUPPORT_BOUNCE_CONFIRMED'=>'ظهرت إشارة ارتداد من الدعم','ENTRY_TIMEFRAME_BREAKOUT_CONFIRMED'=>'تم تأكيد اختراق على فريم الدخول','INSUFFICIENT_CANDLE_HISTORY'=>'عدد الشموع غير كافٍ','MISSING_CONTEXT_CANDLES'=>'بيانات فريم الاتجاه غير متاحة'];
$trendText = ['STRONG_UP'=>'صاعد قوي','UP'=>'صاعد','SIDEWAYS'=>'عرضي','DOWN'=>'هابط','UNKNOWN'=>'غير محدد','STRONG_DOWN'=>'هابط قوي','BULLISH'=>'إيجابي','BEARISH'=>'سلبي','UNCERTAIN'=>'غير واضح'];
$number = fn($v,$digits=3) => is_numeric($v) ? number_format((float)$v,$digits) : '—';
@endphp
@forelse($scanRun->results ?? [] as $stock)
<section class="stock">
<h2>{{ $stock['arabic_name'] ?? $stock['name'] ?? 'سهم' }} <span class="muted">({{ $stock['symbol'] ?? '—' }})</span></h2>
<p class="muted">كل بطاقة بالأسفل تمثل استراتيجية مستقلة للسهم.</p>
@foreach(($stock['profiles'] ?? []) as $profile => $result)
@php
$status = $result['status'] ?? $result['decision'] ?? 'UNKNOWN';
$metrics = $result['metrics'] ?? [];
$candidate = $result['candidate'] ?? [];
$align = $result['timeframe_alignment'] ?? [];
$analysis = $result['timeframe_analyses'] ?? [];
$ai = $result['gemini_analysis'] ?? [];
$reasons = $result['reasons'] ?? [];
$explanations = [];
foreach($reasons as $reason){$explanations[]=$reasonText[$reason] ?? str_replace('_',' ',(string)$reason);}
$context = $align['context'] ?? $analysis['context']['trend'] ?? 'UNKNOWN';
$structure = $align['structure'] ?? $analysis['structure']['trend'] ?? 'UNKNOWN';
$setup = $align['setup'] ?? $analysis['setup']['trend'] ?? 'UNKNOWN';
$badge = in_array($status,['ENTERABLE'])?'good':(in_array($status,['WAIT','WATCHLIST'])?'wait':'bad');
@endphp
<div class="profile">
<h3>{{ $profile === 'Scalping' ? 'مضاربة سريعة (Scalping)' : ($profile === 'Intraday' ? 'تداول يومي (Intraday)' : 'تداول متوسط (Swing)') }}</h3>
<p><span class="tag {{ $badge }}">{{ $statusText[$status] ?? $status }}</span>
@if(isset($metrics['opportunity_score'])) <span class="tag">درجة الفرصة {{ $number($metrics['opportunity_score'],1) }}/100 · {{ $metrics['opportunity_grade'] ?? '' }}</span>@endif</p>
<div class="explain">
<strong>الخلاصة:</strong>
@if($status === 'ENTERABLE') اجتاز السهم شروط الاستراتيجية وقت الفحص. راجع السعر الحالي والتنفيذ قبل اتخاذ أي قرار.
@elseif($status === 'WAIT' || $status === 'WATCHLIST') السهم يستحق المتابعة، لكن شروط الدخول لم تكتمل بعد. لا تطارد السعر خارج منطقة الدخول.
@elseif($status === 'NO_OPPORTUNITY') لا توجد فرصة مطابقة للشروط في هذه اللحظة؛ عدم الدخول هنا قرار مقصود لتجنب صفقة ضعيفة.
@elseif($status === 'REJECTED') لم يتمكن النظام من تقييم السهم بشكل موثوق بسبب مشكلة أو نقص في البيانات.
@else النتيجة تحتاج مراجعة التفاصيل والأسباب أدناه.
@endif
</div>
<div class="cols">
<div><h4>اتجاه الفريمات</h4>
<p>الاتجاه الأكبر: <b>{{ $trendText[$context] ?? $context }}</b></p>
<p>الهيكل السعري: <b>{{ $trendText[$structure] ?? $structure }}</b></p>
<p>فريم الإعداد: <b>{{ $trendText[$setup] ?? $setup }}</b></p>
<p>التوافق: <b>{{ ($align['aligned'] ?? false) ? 'متوافقة' : 'غير متوافقة' }}</b></p></div>
<div><h4>قوة النشاط</h4>
<p>قوة الاتجاه: <b>{{ $number($metrics['trend_score'] ?? null,0) }}/100</b></p>
<p>قوة الحجم: <b>{{ $number($metrics['volume_score'] ?? null,1) }}/100</b></p>
<p>الحجم النسبي RVOL: <b>{{ $number($metrics['relative_volume'] ?? null,2) }}×</b></p>
<p>سرعة الحركة: <b>{{ $number($metrics['speed_score'] ?? null,1) }}/100</b></p></div>
</div>
@if(count($explanations))
<h4>لماذا ظهرت هذه النتيجة؟</h4>
@foreach($explanations as $explanation)<div class="reason">{{ $explanation }}</div>@endforeach
@endif
@if(!empty($candidate))
<h4>خطة الصفقة المقترحة من المحرك</h4>
<div class="grid">
<div class="metric"><small>سعر الدخول</small><strong>{{ $number($candidate['entry'] ?? null) }}</strong></div>
<div class="metric"><small>منطقة الدخول</small><strong>{{ $number($candidate['entry_low'] ?? null) }} – {{ $number($candidate['entry_high'] ?? null) }}</strong></div>
<div class="metric"><small>وقف الخسارة</small><strong>{{ $number($candidate['stop'] ?? null) }}</strong></div>
<div class="metric"><small>الهدف الأول</small><strong>{{ $number($candidate['target1'] ?? null) }}</strong></div>
<div class="metric"><small>الهدف الثاني</small><strong>{{ $number($candidate['target2'] ?? null) }}</strong></div>
<div class="metric"><small>العائد إلى المخاطرة</small><strong>{{ $number($candidate['risk_reward'] ?? null,2) }}</strong></div>
</div>
<p class="note">هذه مستويات ناتجة عن بيانات وقت الفحص وليست ضماناً للتنفيذ أو الربح. يجب التأكد من أن السعر الحالي لم يبتعد عن منطقة الدخول أو يتجاوز الأهداف.</p>
@endif
@if(!empty($result['levels']))
<div class="cols"><div><h4>مناطق الدعم</h4>@forelse($result['levels']['supports'] ?? [] as $level)<p>{{ $number($level['price'] ?? null) }} <span class="muted">· قوة {{ $number($level['strength'] ?? null,1) }}</span></p>@empty<p class="muted">لا توجد مستويات دعم موثقة.</p>@endforelse</div>
<div><h4>مناطق المقاومة</h4>@forelse($result['levels']['resistances'] ?? [] as $level)<p>{{ $number($level['price'] ?? null) }} <span class="muted">· قوة {{ $number($level['strength'] ?? null,1) }}</span></p>@empty<p class="muted">لا توجد مستويات مقاومة موثقة.</p>@endforelse</div></div>
@endif
@if(!empty($ai))
<details><summary>مراجعة Gemini المستقلة — {{ $ai['status'] ?? 'غير معروف' }}</summary>
@if(isset($ai['summary']))<p>{{ $ai['summary'] }}</p>@endif
@if(isset($ai['decision']))<p>قرار Gemini: {{ $statusText[$ai['decision']] ?? $ai['decision'] }} · الثقة: {{ $ai['confidence'] ?? '—' }}</p>@endif
@if(!empty($ai['key_evidence']))<h4>الأدلة</h4><ul>@foreach($ai['key_evidence'] as $item)<li>{{ $item }}</li>@endforeach</ul>@endif
@if(!empty($ai['risks']))<h4>المخاطر</h4><ul>@foreach($ai['risks'] as $item)<li>{{ $item }}</li>@endforeach</ul>@endif
@if(isset($ai['error']))<p class="reason">تعذر إكمال المراجعة: {{ $ai['error'] }}</p>@endif
<details><summary>بيانات المراجعة الكاملة</summary><pre>{{ json_encode($ai,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) }}</pre></details>
</details>
@endif
<details><summary>التفاصيل الفنية الخام (للمراجعة المتقدمة)</summary><pre>{{ json_encode($result,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) }}</pre></details>
</div>
@endforeach
</section>
@empty
<div class="panel">لا توجد نتائج أسهم محفوظة في عملية الفحص هذه.</div>
@endforelse
<p class="note">تنبيه: درجة الفرصة ليست احتمالاً إحصائياً للربح. النتائج أداة بحث وتحليل، وليست توصية استثمارية أو ضماناً للصفقات.</p>
</body></html>