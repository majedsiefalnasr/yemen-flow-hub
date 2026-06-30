<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>اللجنة الوطنية لتنظيم وتمويل الواردات</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700;800&display=swap" rel="stylesheet">
    <style>body{font-family:Cairo,sans-serif}</style>
</head>
<body class="bg-slate-100 text-slate-800">
<div class="max-w-[1800px] mx-auto p-4 space-y-4">
    <header class="bg-white border rounded-xl shadow-sm p-4 flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-extrabold">اللجنة الوطنية لتنظيم وتمويل الواردات</h1>
            <p class="text-sm text-slate-500">صفحة اختبار API</p>
        </div>
        <button id="logoutBtn" class="px-3 py-2 text-sm rounded-lg bg-rose-600 text-white hover:bg-rose-700">تسجيل خروج</button>
    </header>

    <div id="statusBar" class="bg-white border rounded-xl shadow-sm p-3 text-sm">لم يتم تسجيل الدخول</div>

    <div class="grid grid-cols-1 xl:grid-cols-12 gap-4">
        <section class="xl:col-span-8 space-y-4">
            <div class="bg-white border rounded-xl p-4">
                <div class="flex items-center justify-between mb-3 gap-3">
                    <h2 class="font-bold text-lg">تسجيل سريع</h2>
                    <input id="userFilter" class="w-80 max-w-full border rounded-lg px-3 py-2 text-sm" placeholder="بحث">
                </div>
                <div id="usersWrap" class="max-h-80 overflow-auto border rounded-lg"></div>
            </div>

            <div class="bg-white border rounded-xl p-4">
                <div class="flex items-center justify-between mb-3 gap-3">
                    <h2 class="font-bold text-lg">المجموعات</h2>
                    <input id="epFilter" class="w-80 max-w-full border rounded-lg px-3 py-2 text-sm" placeholder="بحث endpoint">
                </div>
                <div id="tabs" class="flex flex-wrap gap-2 mb-4"></div>
                <div id="cards" class="space-y-3"></div>
            </div>
        </section>

        <aside class="xl:col-span-4">
            <div class="sticky top-4 bg-white border rounded-xl p-4 space-y-3">
                <h2 class="font-bold text-lg">نتيجة آخر طلب</h2>
                <div id="respMeta" class="text-sm p-3 bg-slate-100 rounded-lg">لا يوجد طلب بعد</div>
                <pre id="reqBody" class="p-3 rounded-lg bg-slate-900 text-slate-100 text-xs overflow-auto max-h-40"></pre>
                <pre id="respBody" class="p-3 rounded-lg bg-slate-900 text-slate-100 text-xs overflow-auto max-h-[600px]"></pre>
            </div>
        </aside>
    </div>
</div>

<script>window.SEEDED_USERS = @json($users);</script>
<script>
const API_BASE='/api', TOKEN_KEY='yfh_api_token', USER_KEY='yfh_current_user', TAB_KEY='yfh_tab', LAST_ID_KEY='yfh_last_id', PW='password';
const EP=[
{k:'auth',a:'المصادقة',e:[['POST','/auth/login','تسجيل الدخول',['email','password']],['POST','/auth/logout','تسجيل الخروج',[]],['GET','/auth/me','المستخدم الحالي',[]]]},
{k:'banks',a:'البنوك',e:[['GET','/banks','قائمة البنوك',[]],['POST','/banks','إضافة بنك',['name','code']],['GET','/banks/{id}','تفاصيل بنك',['id']],['PUT','/banks/{id}','تعديل بنك',['id','name','code','is_active']],['DELETE','/banks/{id}','حذف بنك',['id']]]},
{k:'users',a:'المستخدمون',e:[['GET','/users','قائمة المستخدمين',['role','bank_id','is_active']],['POST','/users','إضافة مستخدم',['name','email','password','role','bank_id']],['GET','/users/{id}','تفاصيل مستخدم',['id']],['PUT','/users/{id}','تعديل مستخدم',['id','name','email','role','bank_id','is_active']],['DELETE','/users/{id}','تعطيل مستخدم',['id']]]},
{k:'requests',a:'طلبات التمويل',e:[['GET','/requests','قائمة الطلبات',['status','bank_id','search','from_date','to_date','claim_filter']],['POST','/requests','إنشاء طلب',['merchant_id','currency','amount','supplier_name','goods_description','port_of_entry','notes']],['GET','/requests/{id}','تفاصيل طلب',['id']],['PUT','/requests/{id}','تعديل طلب',['id','merchant_id','currency','amount','supplier_name','goods_description','port_of_entry','notes']],['DELETE','/requests/{id}','حذف طلب',['id']],['GET','/requests/{id}/history','سجل المراحل',['id']]]},
{k:'workflow',a:'سير العمل',e:[['POST','/workflow/{id}/submit','إرسال الطلب',['id','reason']],['POST','/workflow/{id}/bank-approve','موافقة البنك',['id']],['POST','/workflow/{id}/bank-reject','رفض البنك',['id','reason']],['POST','/workflow/{id}/return-to-entry','إعادة للإدخال',['id','reason']],['POST','/workflow/{id}/support-claim','حجز الطلب',['id']],['POST','/workflow/{id}/support-release','إلغاء الحجز',['id']],['POST','/workflow/{id}/support-approve','موافقة لجنة المساندة',['id']],['POST','/workflow/{id}/support-reject','رفض لجنة المساندة',['id','reason']],['POST','/workflow/{id}/swift-upload','رفع SWIFT',['id','file']],['POST','/workflow/{id}/finalize-decision','إنهاء القرار',['id','decision']]]},
{k:'voting',a:'التصويت',e:[['GET','/voting','طلبات التصويت',[]],['GET','/voting/{id}','تفاصيل تصويت',['id']],['POST','/voting/{id}/vote','إرسال تصويت',['id','vote','justification']],['POST','/voting/{id}/director-decide','قرار المدير',['id','vote','justification']],['POST','/voting/{id}/override','قرار مدير اللجنة التنفيذية',['id','decision','justification']]]},
{k:'documents',a:'المستندات',e:[['POST','/requests/{id}/documents','رفع مستند',['id','file']],['DELETE','/documents/{id}','حذف مستند',['id']],['GET','/documents/{id}/download','تحميل مستند',['id']]]},
{k:'customs',a:'وثيقة تأكيد المصارفة الخارجية',e:[['POST','/customs/{request_id}/generate','إصدار الوثيقة',['request_id']],['GET','/customs/{id}','تفاصيل الوثيقة',['id']],['GET','/customs/{id}/download','تحميل الوثيقة',['id']]]},
{k:'audit',a:'سجلات التدقيق',e:[['GET','/audit','عرض التدقيق',['user_id','action','from_date','to_date']]]},
{k:'notifications',a:'الإشعارات',e:[['GET','/notifications','إشعاراتي',[]],['POST','/notifications/{id}/read','تعليم كمقروء',['id']],['POST','/notifications/read-all','تعليم الكل',[]]]},
{k:'dashboard',a:'لوحة المعلومات',e:[['GET','/dashboard/stats','إحصائيات اللوحة',[]]]},
{k:'reports',a:'التقارير',e:[['GET','/reports/workflow','تقرير سير العمل',[]],['GET','/reports/voting','تقرير التصويت',[]]]},
{k:'merchants',a:'المستوردون',e:[['GET','/merchants','قائمة المستوردين',['bank_id','is_active','search']],['POST','/merchants','إضافة مستورد',['name','commercial_register','tax_number','owner_name','phone','email','address','bank_id']],['GET','/merchants/{id}','تفاصيل مستورد',['id']],['PUT','/merchants/{id}','تعديل مستورد',['id','name','commercial_register','tax_number','owner_name','phone','email','address','bank_id','is_active']],['DELETE','/merchants/{id}','حذف مستورد',['id']]]},
{k:'doc-types',a:'أنواع المستندات',e:[['GET','/document-types','قائمة أنواع المستندات',[]],['POST','/document-types','إضافة نوع',['slug','name_ar','name_en','is_required','is_active','sort_order']],['PUT','/document-types/{id}','تعديل نوع',['id','slug','name_ar','name_en','is_required','is_active','sort_order']],['DELETE','/document-types/{id}','حذف نوع',['id']]]}
];

let merchantOptions = [];

const getToken=()=>localStorage.getItem(TOKEN_KEY);
const getUser=()=>JSON.parse(localStorage.getItem(USER_KEY)||'null');

async function fetchMerchants() {
  const tk=getToken(); if(!tk) return;
  const res = await fetch(API_BASE+'/merchants', {headers:{Accept:'application/json',Authorization:`Bearer ${tk}`}});
  const data = await res.json().catch(()=>null);
  merchantOptions = data?.data?.data || data?.data || [];
}

function updateStatus(){
  const u=getUser();
  document.getElementById('statusBar').textContent=u?`المستخدم الحالي: ${u.name} (${u.role_label}) - ${u.bank_name||'اللجنة الوطنية لتنظيم وتمويل الواردات'}`:'لم يتم تسجيل الدخول';
}

function groupUsers(list){const g={};list.forEach(u=>(g[u.role_label]=g[u.role_label]||[]).push(u));return g;}
function renderUsers(){
  const q=document.getElementById('userFilter').value.toLowerCase();
  const users=window.SEEDED_USERS.filter(u=>[u.name,u.email,u.role_label,u.bank_name||''].join(' ').toLowerCase().includes(q));
  const g=groupUsers(users);
  document.getElementById('usersWrap').innerHTML=Object.entries(g).map(([role,arr])=>`<div class="border-b last:border-b-0"><div class="bg-slate-50 px-3 py-2 text-sm font-bold">${role}</div>${arr.map(u=>`<div class="px-3 py-2 flex items-center justify-between gap-3 ${getUser()?.email===u.email?'bg-emerald-50':''}"><div class="text-sm"><div class="font-semibold">${u.name}</div><div class="text-slate-500">${u.email} - ${u.bank_name||'اللجنة الوطنية لتنظيم وتمويل الواردات'}</div></div><button class="px-3 py-1.5 rounded-lg bg-blue-600 text-white text-sm hover:bg-blue-700" onclick="loginQuick('${u.email}')">دخول</button></div>`).join('')}</div>`).join('');
}

async function callApi(method,path,body=null,isMultipart=false){
  const h={Accept:'application/json'}; const tk=getToken(); if(tk) h.Authorization=`Bearer ${tk}`;
  if(!isMultipart && body && method!=='GET') h['Content-Type']='application/json';
  const opt={method,headers:h}; if(body&&method!=='GET') opt.body=isMultipart?body:JSON.stringify(body);
  const res=await fetch(API_BASE+path,opt); const data=await res.json().catch(()=>null);
  renderResponse({method,path,status:res.status,data,body});
  return {status:res.status,data};
}

function renderResponse(r){
  document.getElementById('respMeta').textContent=`${r.method} ${r.path} -> ${r.status}`;
  document.getElementById('reqBody').textContent=r.body?JSON.stringify(r.body,null,2):'';
  document.getElementById('respBody').textContent=JSON.stringify(r.data||{},null,2);
  const id=r.data?.data?.id||r.data?.id||null; if(id) localStorage.setItem(LAST_ID_KEY,id);
}

function renderTabs(){
  const active=localStorage.getItem(TAB_KEY)||EP[0].k;
  document.getElementById('tabs').innerHTML=EP.map(t=>`<button onclick="setTab('${t.k}')" class="px-3 py-1.5 rounded-lg text-sm ${t.k===active?'bg-blue-600 text-white':'bg-slate-200 hover:bg-slate-300'}">${t.a}</button>`).join('');
}
function setTab(k){localStorage.setItem(TAB_KEY,k);renderTabs();renderCards();}

function fieldInput(key,f){
  if (f==='file') return `<input type="file" data-k="${key}" data-f="${f}" class="w-full border rounded-lg px-2 py-1.5">`;
  if (f==='merchant_id') {
    const options = merchantOptions.map(m=>`<option value="${m.id}">${m.name}</option>`).join('');
    return `<select data-k="${key}" data-f="${f}" class="w-full border rounded-lg px-2 py-1.5"><option value="">اختر مستورداً</option>${options}</select>`;
  }
  return `<input data-k="${key}" data-f="${f}" class="w-full border rounded-lg px-2 py-1.5" value="${localStorage.getItem('v_'+key+'_'+f)||''}">`;
}

function card(method,path,title,fields,key){
  const locked=!getToken()&&path!='/auth/login';
  return `<div class="border rounded-xl p-3 ${locked?'opacity-60':''}" data-search="${(method+' '+path+' '+title).toLowerCase()}">
    <div class="text-xs mb-1">${method} ${API_BASE+path}</div>
    <div class="text-sm mb-3 font-semibold">${title}</div>
    <div class="grid grid-cols-1 md:grid-cols-2 gap-2">${fields.map(f=>`<div><label class="text-xs text-slate-500">${f}</label>${fieldInput(key,f)}</div>`).join('')}</div>
    <div class="mt-3"><button class="px-3 py-1.5 rounded-lg bg-emerald-600 text-white text-sm hover:bg-emerald-700" onclick="run('${key}','${method}','${path}',${fields.includes('file')})">تنفيذ</button></div>
  </div>`;
}

function renderCards(){
  const active=localStorage.getItem(TAB_KEY)||EP[0].k; const tab=EP.find(x=>x.k===active)||EP[0]; const q=document.getElementById('epFilter').value.toLowerCase();
  document.getElementById('cards').innerHTML=tab.e.map((e,i)=>card(e[0],e[1],e[2],e[3],`${active}_${i}`)).join('');
  document.querySelectorAll('#cards [data-search]').forEach(el=>{if(!el.dataset.search.includes(q))el.style.display='none';});
  const last=localStorage.getItem(LAST_ID_KEY); if(last) document.querySelectorAll('input[data-f="id"],input[data-f="request_id"]').forEach(i=>i.value=last);
}

async function run(key,method,path,multipart){
  if(!getToken()&&path!='/auth/login') return;
  let url=path, q={}, body=multipart?new FormData():{};
  document.querySelectorAll(`[data-k="${key}"]`).forEach(inp=>{
    const f=inp.dataset.f;
    if(inp.type==='file'){if(inp.files[0]) body.append('file',inp.files[0]); return;}
    const v=inp.value.trim(); localStorage.setItem('v_'+key+'_'+f,v); if(!v) return;
    if(url.includes('{'+f+'}')) url=url.replace('{'+f+'}',encodeURIComponent(v));
    else if(method==='GET') q[f]=v;
    else multipart?body.append(f,v):body[f]=v;
  });
  if(method==='GET'&&Object.keys(q).length) url+='?'+new URLSearchParams(q).toString();
  await callApi(method,url,method==='GET'?null:body,multipart);
}

async function loginQuick(email){
  const r=await callApi('POST','/auth/login',{email,password:PW});
  const payload=r.data?.data||{};
  if(r.status===200 && payload.token){
    localStorage.setItem(TOKEN_KEY,payload.token);
    localStorage.setItem(USER_KEY,JSON.stringify(payload.user));
    await fetchMerchants();
    updateStatus(); renderUsers(); renderCards();
  }
}

async function logoutQuick(){
  if(getToken()) await callApi('POST','/auth/logout');
  localStorage.removeItem(TOKEN_KEY); localStorage.removeItem(USER_KEY);
  merchantOptions = [];
  updateStatus(); renderUsers(); renderCards();
}

document.getElementById('userFilter').addEventListener('input',renderUsers);
document.getElementById('epFilter').addEventListener('input',renderCards);
document.getElementById('logoutBtn').addEventListener('click',logoutQuick);

(async function init(){
  if(getToken()) await fetchMerchants();
  updateStatus();
  renderUsers();
  renderTabs();
  renderCards();
})();
</script>
</body>
</html>
