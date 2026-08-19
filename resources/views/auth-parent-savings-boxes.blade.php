<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cajas de ahorro (Padre)</title>
    <link rel="stylesheet" href="/css/styles.css">
    <style>
        .savings-summary-card{cursor:pointer;}
        .savings-calculator{background:#f8fafc;color:#10234a;border:1px solid #dbe3ef;border-radius:12px;padding:12px;}
        .savings-metrics{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:8px;margin-top:8px;}
        .savings-metric{background:#fff;border:1px solid #e5e7eb;border-radius:10px;padding:10px;}
        .savings-metric-label{margin:0;color:#4b5563;font-size:.82rem;font-weight:700;}
        .savings-metric-value{margin:4px 0 0;color:#021b57;font-weight:800;}
        .savings-member-list{display:grid;gap:8px;margin-top:8px;}
        .savings-member-check{display:flex;align-items:center;gap:10px;border:1px solid #dbe3ef;border-radius:10px;padding:10px 12px;background:#f8fafc;color:#021b57;font-weight:700;}
        .savings-summary-top{align-items:center;gap:.75rem;}
        .savings-expand-btn{width:2.2rem;height:2.2rem;border:0;border-radius:999px;background:rgba(255,255,255,.16);color:#fff;font-size:1.1rem;line-height:1;transition:transform .18s ease,background .18s ease;}
        .savings-summary-card.is-open .savings-expand-btn{transform:rotate(180deg);}
        .savings-member-detail{display:grid;gap:8px;margin-top:10px;padding-top:10px;border-top:1px solid rgba(255,255,255,.2);}
        .savings-member-row{display:grid;grid-template-columns:1fr;gap:6px;background:rgba(255,255,255,.1);border:1px solid rgba(255,255,255,.18);border-radius:10px;padding:10px;}
        .savings-member-name{margin:0;color:#fff;font-weight:800;}
        .savings-member-numbers{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:8px;}
        .savings-member-number{margin:0;color:#dbeafe;font-size:.84rem;}
        .savings-member-number strong{display:block;color:#fff;font-size:.94rem;}
        @media (max-width: 640px){.savings-member-numbers{grid-template-columns:1fr;}}
        @media (max-width: 640px){.savings-metrics{grid-template-columns:1fr;}}
    </style>
</head>
<body class="app-bg">
<main class="layout">
    <div id="sidebar-overlay" class="sidebar-overlay"></div>
    <aside id="sidebar" class="sidebar">
        <button id="sidebar-close" class="sidebar-close" type="button">x</button>
        <div class="sidebar-logo-wrap"><img class="sidebar-logo" src="/img/domus_logo.png" alt="Domus logo"></div>
        <section class="profile-card"><p id="sidebar-user-name" class="profile-name">Usuario</p></section>
        <div id="sidebar-scroll" class="sidebar-scroll">
            <nav class="sidebar-nav">
                <a class="sidebar-link" href="/account"><span class="nav-icon">&bull;</span><span>Inicio</span></a>
                <a class="sidebar-link" href="/account/users"><span class="nav-icon">&bull;</span><span>Usuarios</span></a>
                <a class="sidebar-link" href="/parent/loans"><span class="nav-icon">&bull;</span><span>Prestamos</span></a>
                <a class="sidebar-link" href="/parent/transfers"><span class="nav-icon">&bull;</span><span>Dar dinero</span></a>
                <a class="sidebar-link" href="/parent/withdrawals"><span class="nav-icon">&bull;</span><span>Retirar dinero</span></a>
                <a class="sidebar-link" href="/parent/allowances"><span class="nav-icon">&bull;</span><span>Mesadas</span></a>
                <a class="sidebar-link is-active" href="/parent/savings-boxes"><span class="nav-icon">&bull;</span><span>Cajas de ahorro</span></a>
                <a class="sidebar-link" href="/parent/tasks"><span class="nav-icon">&bull;</span><span>Tareas</span></a>
                <a class="sidebar-link" href="/parent/domus-points"><span class="nav-icon">&bull;</span><span>Puntos Domus</span></a>
                <a class="sidebar-link" href="/account/education"><span class="nav-icon">&bull;</span><span>Educacion</span></a>
            </nav>
            <div id="scroll-hint" class="scroll-hint">Desliza para ver mas</div>
        </div>
    </aside>

    <section class="page content card">
        <div class="top-row"><button id="menu-btn" class="btn blue-btn menu-toggle" type="button">Menu</button><button id="logout-btn" class="btn btn-inline gold-btn right" type="button">Cerrar sesion</button></div>
        <p class="brand mt-2">DOMUS</p>
        <h1>Cajas de ahorro</h1>
        <p class="subtitle">Define la duracion, el maximo por ciclo y el beneficio que se entregara al final.</p>

        <div class="top-row mt-1">
            <button id="savings-tab-list" class="btn blue-btn btn-inline" type="button">Mis cajas</button>
            <button id="savings-tab-create" class="btn gold-btn btn-inline" type="button">Crear caja</button>
        </div>

        <section id="savings-list-panel" class="mt-2"><div id="savings-list"></div></section>

        <section id="savings-form-panel" class="mt-2 is-hidden">
            <form id="savings-form" class="form">
                <div class="field"><label for="savings-name">Nombre de la caja</label><input id="savings-name" class="input" type="text" maxlength="120" placeholder="Ejemplo: Bicicleta nueva" required></div>
                <div class="field">
                    <label for="duration-months">Duracion</label>
                    <select id="duration-months" class="input" required>
                        @foreach (config('savings_box.months_available', [1, 2, 3]) as $months)
                            <option value="{{ (int) $months }}">{{ (int) $months }} {{ (int) $months === 1 ? 'mes' : 'meses' }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="field"><label for="max-per-cycle">Maximo de ahorro cada {{ (int) config('savings_box.days_per_cycle', 14) }} dias</label><input id="max-per-cycle" class="input" type="text" inputmode="decimal" placeholder="10.00" required></div>
                <div class="field"><label for="benefit-percent">Beneficio al finalizar (%)</label><input id="benefit-percent" class="input" type="number" min="0" max="1000" step="0.01" value="0" required></div>
                <div class="field">
                    <label class="legal-check"><input id="allow-early-withdrawal" type="checkbox"> Permitir retiro anticipado. El beneficio se calculara solamente sobre el dinero que permanezca en la fecha de corte.</label>
                </div>

                <div class="field">
                    <label>Quienes pueden usarla</label>
                    <div class="choice-row">
                        <label class="choice-pill"><input id="audience-all" name="savings-audience" type="radio" value="all" checked> Habilitar para todas las personas</label>
                        <label class="choice-pill"><input id="audience-specific" name="savings-audience" type="radio" value="specific"> Habilitar para integrantes especificos</label>
                    </div>
                    <div id="specific-members-field" class="is-hidden">
                        <p id="savings-empty-message" class="legal-note is-hidden">Crea un usuario para poder elegir integrantes especificos.</p>
                        <div id="savings-members-list" class="savings-member-list"></div>
                    </div>
                </div>

                <section class="savings-calculator mt-1" aria-live="polite">
                    <p class="quick-card-title">Resumen calculado</p>
                    <div class="savings-metrics">
                        <div class="savings-metric"><p class="savings-metric-label">Fecha de corte estimada</p><p id="plan-cutoff" class="savings-metric-value">-</p></div>
                        <div class="savings-metric"><p class="savings-metric-label">Cantidad de ciclos</p><p id="plan-cycles" class="savings-metric-value">0</p></div>
                        <div class="savings-metric"><p class="savings-metric-label">Maximo total por integrante</p><p id="plan-maximum" class="savings-metric-value">$0.00</p></div>
                        <div class="savings-metric"><p class="savings-metric-label">Beneficio por cada $100 al corte</p><p id="plan-benefit" class="savings-metric-value">$0.00</p></div>
                    </div>
                    <p class="choice-helper">El ultimo ciclo puede ser parcial y conserva su limite completo. Los valores definitivos se congelan al crear la caja.</p>
                </section>
                <div id="savings-feedback" class="feedback-box is-hidden"></div>
                <div class="top-row mt-2"><button id="savings-back" class="btn btn-inline blue-btn" type="button">Volver</button><button id="savings-submit" class="btn btn-inline gold-btn" type="submit">Crear caja</button></div>
            </form>
        </section>
    </section>
</main>

<script>
const API_BASE='/api',TOKEN_KEY='parent_auth_token',CYCLE_DAYS=Number(@json((int) config('savings_box.days_per_cycle', 14)));
let savingsTargets=[],savingsBoxesById=new Map();
const logoutButton=document.getElementById('logout-btn'),menuButton=document.getElementById('menu-btn'),sidebar=document.getElementById('sidebar'),sidebarUserName=document.getElementById('sidebar-user-name'),sidebarClose=document.getElementById('sidebar-close'),sidebarOverlay=document.getElementById('sidebar-overlay'),sidebarScroll=document.getElementById('sidebar-scroll'),scrollHint=document.getElementById('scroll-hint');
const listTab=document.getElementById('savings-tab-list'),createTab=document.getElementById('savings-tab-create'),listPanel=document.getElementById('savings-list-panel'),savingsList=document.getElementById('savings-list'),formPanel=document.getElementById('savings-form-panel'),savingsForm=document.getElementById('savings-form'),savingsBack=document.getElementById('savings-back'),savingsFeedback=document.getElementById('savings-feedback');
const savingsName=document.getElementById('savings-name'),durationMonths=document.getElementById('duration-months'),maxPerCycle=document.getElementById('max-per-cycle'),benefitPercent=document.getElementById('benefit-percent'),allowEarlyWithdrawal=document.getElementById('allow-early-withdrawal'),audienceAll=document.getElementById('audience-all'),audienceSpecific=document.getElementById('audience-specific'),specificMembersField=document.getElementById('specific-members-field'),membersList=document.getElementById('savings-members-list'),emptyMessage=document.getElementById('savings-empty-message'),planCutoff=document.getElementById('plan-cutoff'),planCycles=document.getElementById('plan-cycles'),planMaximum=document.getElementById('plan-maximum'),planBenefit=document.getElementById('plan-benefit');
function getToken(){return localStorage.getItem(TOKEN_KEY);}
function clearToken(){localStorage.removeItem(TOKEN_KEY);}
function openSidebar(){sidebar.classList.add('is-open');sidebarOverlay.classList.add('is-open');}
function closeSidebar(){sidebar.classList.remove('is-open');sidebarOverlay.classList.remove('is-open');}
function updateScrollHint(){const canScroll=sidebarScroll.scrollHeight>sidebarScroll.clientHeight;const nearBottom=sidebarScroll.scrollTop+sidebarScroll.clientHeight>=sidebarScroll.scrollHeight-4;scrollHint.classList.toggle('is-visible',canScroll&&!nearBottom);}
function escapeHtml(value){return String(value??'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#39;');}
function toMoney(value){const amount=Number(value||0);return '$'+amount.toLocaleString('en-US',{minimumFractionDigits:2,maximumFractionDigits:2});}
function toMoneyFromCents(value){return toMoney(Number(value||0)/100);}
function formatDate(value){if(!value){return '-';}const datePart=String(value).slice(0,10);const parts=datePart.split('-');if(parts.length!==3){return String(value);}return parts[2]+'/'+parts[1]+'/'+parts[0];}
function setFeedback(message,type){savingsFeedback.textContent=message;savingsFeedback.className='feedback-box '+type;}
function clearFeedback(){savingsFeedback.textContent='';savingsFeedback.className='feedback-box is-hidden';}
async function apiRequest(path,method,payload){const token=getToken();const headers={Accept:'application/json','Content-Type':'application/json'};if(token){headers.Authorization='Bearer '+token;}const response=await fetch(API_BASE+path,{method,headers,body:payload?JSON.stringify(payload):undefined});const data=await response.json().catch(()=>({}));if(!response.ok){throw {status:response.status,data};}return data;}
function syncChoicePillState(){document.querySelectorAll('.choice-pill').forEach(pill=>{const input=pill.querySelector('input');pill.classList.toggle('is-selected',Boolean(input&&input.checked));});}
function getAudience(){return audienceSpecific.checked?'specific':'all';}
function getSelectedMemberIds(){return Array.from(document.querySelectorAll('.savings-member-checkbox:checked')).map(input=>Number(input.value));}
function updateAudienceState(){specificMembersField.classList.toggle('is-hidden',getAudience()!=='specific');syncChoicePillState();}
function parseMoney(value){const normalized=String(value||'').trim().replace(',','.');if(!/^\d+(?:\.\d{1,2})?$/.test(normalized)){return 0;}return Math.round(Number(normalized)*100);}
function addMonthsNoOverflow(source,months){const result=new Date(source.getTime()),day=result.getDate();result.setDate(1);result.setMonth(result.getMonth()+Number(months||0));const lastDay=new Date(result.getFullYear(),result.getMonth()+1,0).getDate();result.setDate(Math.min(day,lastDay));return result;}
function updateCalculator(){const start=new Date(),cutoff=addMonthsNoOverflow(start,Number(durationMonths.value||0)),durationDays=Math.max(1,Math.ceil((cutoff-start)/86400000)),cycles=Math.max(1,Math.ceil(durationDays/CYCLE_DAYS)),maxCycleCents=parseMoney(maxPerCycle.value),benefit=Math.max(0,Number(benefitPercent.value)||0);planCutoff.textContent=formatDate(cutoff.toISOString().slice(0,10));planCycles.textContent=String(cycles)+' de '+CYCLE_DAYS+' dias';planMaximum.textContent=toMoneyFromCents(maxCycleCents*cycles);planBenefit.textContent=toMoney(benefit);}
function showList(){listPanel.classList.remove('is-hidden');formPanel.classList.add('is-hidden');listTab.classList.remove('gold-btn');listTab.classList.add('blue-btn');createTab.classList.remove('blue-btn');createTab.classList.add('gold-btn');}
function showCreate(){listPanel.classList.add('is-hidden');formPanel.classList.remove('is-hidden');createTab.classList.remove('gold-btn');createTab.classList.add('blue-btn');listTab.classList.remove('blue-btn');listTab.classList.add('gold-btn');renderMembersForSavings();clearFeedback();updateAudienceState();updateCalculator();}
function renderMembersForSavings(){if(savingsTargets.length===0){emptyMessage.classList.remove('is-hidden');membersList.innerHTML='';return;}emptyMessage.classList.add('is-hidden');membersList.innerHTML=savingsTargets.map(target=>'<label class="savings-member-check"><input class="savings-member-checkbox" type="checkbox" value="'+Number(target.user_id)+'"> <span>'+escapeHtml(target.name)+' (@'+escapeHtml(target.username||'sin_username')+')</span></label>').join('');}
function getAudienceLabel(box){if(box.audience==='all'){return 'Todas las personas';}const names=(box.members||[]).map(member=>member.name).filter(Boolean);return names.length?('Integrantes: '+names.join(', ')):'Integrantes especificos';}
function renderSavingsAccount(account,box){const principal=Number(account.principal_cents||0),legacy=box.benefit_mode==='legacy_accrual',benefit=legacy?Number(account.earned_interest_cents||0):Math.round(principal*Math.max(0,Number(box.benefit_percent||0))/100),settled=Number(account.settled_total_cents||0),user=account.user||{};return '<div class="savings-member-row"><p class="savings-member-name">'+escapeHtml(user.name||'Integrante')+' (@'+escapeHtml(user.username||'sin_username')+')</p><div class="savings-member-numbers"><p class="savings-member-number">'+(box.status==='completed'?'Capital liquidado':'Ahorrado')+'<strong>'+toMoneyFromCents(box.status==='completed'?account.settled_principal_cents:principal)+'</strong></p><p class="savings-member-number">Beneficio '+(box.status==='completed'?'entregado':'estimado')+'<strong>'+toMoneyFromCents(box.status==='completed'?account.settled_benefit_cents:benefit)+'</strong></p><p class="savings-member-number">'+(box.status==='completed'?'Total entregado':'Total estimado')+'<strong>'+toMoneyFromCents(box.status==='completed'?settled:principal+benefit)+'</strong></p></div></div>';}
function renderSavingsBox(box){const accounts=box.accounts||[],completed=box.status==='completed',legacy=box.benefit_mode==='legacy_accrual',principalTotal=accounts.reduce((sum,account)=>sum+Number(completed?account.settled_principal_cents:account.principal_cents||0),0),benefitTotal=accounts.reduce((sum,account)=>sum+Number(completed?account.settled_benefit_cents:(legacy?account.earned_interest_cents:Math.round(Number(account.principal_cents||0)*Math.max(0,Number(box.benefit_percent||0))/100))),0),accountRows=accounts.length?accounts.map(account=>renderSavingsAccount(account,box)).join(''):'<p class="quick-card-subtitle">No hay integrantes habilitados para esta caja.</p>',status=completed?'Completada':'Activa',planText=legacy?'Plan anterior':(Number(box.cycle_count||0)+' ciclos de '+Number(box.cycle_days||0)+' dias | Maximo por ciclo: '+toMoneyFromCents(box.max_per_cycle_cents||0)+' | Maximo total: '+toMoneyFromCents(box.max_amount_cents||0));return '<section class="quick-card mt-1 savings-summary-card" data-savings-id="'+Number(box.id)+'"><div class="top-row savings-summary-top"><div><p class="quick-card-title">'+escapeHtml(box.name)+'</p><p class="quick-card-subtitle">'+escapeHtml(getAudienceLabel(box))+' | Corte: '+escapeHtml(formatDate(box.cutoff_at||box.delivery_date))+' | Beneficio: '+Number(box.benefit_percent||box.annual_gain_percent||0).toFixed(2)+'%</p></div><button class="savings-expand-btn" type="button" aria-label="Ver detalle">v</button></div><p class="quick-card-subtitle">'+status+' | '+planText+'</p><p class="quick-card-subtitle">Retiro anticipado: '+(box.allow_early_withdrawal?'Permitido':'No permitido')+' | '+(completed?'Capital liquidado: ':'Ahorrado ahora: ')+toMoneyFromCents(principalTotal)+' | '+(completed?'Beneficio entregado: ':'Beneficio estimado: ')+toMoneyFromCents(benefitTotal)+'</p><div class="savings-member-detail is-hidden">'+accountRows+'</div></section>';}
function bindSavingsBoxes(){savingsList.querySelectorAll('.savings-summary-card').forEach(card=>card.addEventListener('click',()=>{card.classList.toggle('is-open');const detail=card.querySelector('.savings-member-detail');if(detail){detail.classList.toggle('is-hidden');}}));}
async function loadFamilyMembers(){const response=await apiRequest('/family-members','GET');savingsTargets=response.loan_targets||[];}
async function loadSavingsBoxes(){try{const response=await apiRequest('/savings-boxes','GET');const boxes=response.savings_boxes||[];savingsBoxesById=new Map(boxes.map(box=>[Number(box.id),box]));if(boxes.length===0){savingsList.innerHTML='<section class="quick-card"><p class="quick-card-title">Sin cajas de ahorro</p><p class="quick-card-subtitle">Todavia no has creado cajas.</p><button id="empty-create-savings-btn" class="btn gold-btn btn-inline mt-1" type="button">Crear caja</button></section>';document.getElementById('empty-create-savings-btn').addEventListener('click',showCreate);return;}savingsList.innerHTML=boxes.map(renderSavingsBox).join('');bindSavingsBoxes();}catch(error){savingsList.innerHTML='<section class="quick-card"><p class="quick-card-title">Error</p><p class="quick-card-subtitle">'+escapeHtml(error?.data?.message||'No se pudieron cargar cajas de ahorro.')+'</p></section>';}}
savingsForm.addEventListener('submit',async event=>{event.preventDefault();clearFeedback();const audience=getAudience(),memberIds=getSelectedMemberIds();if(savingsTargets.length===0){setFeedback('Crea al menos un integrante antes de crear una caja de ahorro.','feedback-error');return;}if(audience==='specific'&&memberIds.length===0){setFeedback('Selecciona al menos un integrante.','feedback-error');return;}const payload={name:savingsName.value.trim(),duration_months:Number(durationMonths.value),max_per_cycle:maxPerCycle.value.trim(),benefit_percent:Number(benefitPercent.value)||0,allow_early_withdrawal:allowEarlyWithdrawal.checked,audience:audience,member_user_ids:memberIds};try{await apiRequest('/savings-boxes','POST',payload);savingsForm.reset();benefitPercent.value='0';audienceAll.checked=true;updateAudienceState();updateCalculator();setFeedback('Caja de ahorro creada correctamente.','feedback-success');await loadSavingsBoxes();}catch(error){const errors=error?.data?.errors;setFeedback(errors?Object.values(errors).flat().join(' '):(error?.data?.message||'No se pudo crear la caja.'),'feedback-error');}});
savingsBack.addEventListener('click',showList);
listTab.addEventListener('click',showList);
createTab.addEventListener('click',showCreate);
durationMonths.addEventListener('change',updateCalculator);
maxPerCycle.addEventListener('input',updateCalculator);
benefitPercent.addEventListener('input',updateCalculator);
audienceAll.addEventListener('change',updateAudienceState);
audienceSpecific.addEventListener('change',updateAudienceState);
Array.from(document.querySelectorAll('.choice-pill input')).forEach(input=>input.addEventListener('change',syncChoicePillState));
logoutButton.addEventListener('click',async()=>{try{await apiRequest('/logout','POST');}catch(error){}finally{clearToken();window.location.href='/login';}});
menuButton.addEventListener('click',()=>{if(sidebar.classList.contains('is-open')){closeSidebar();return;}openSidebar();updateScrollHint();});
sidebarClose.addEventListener('click',closeSidebar);
sidebarOverlay.addEventListener('click',closeSidebar);
sidebarScroll.addEventListener('scroll',updateScrollHint);
window.addEventListener('resize',()=>{if(window.innerWidth>=768){closeSidebar();}updateScrollHint();});
(async function bootstrap(){updateScrollHint();updateCalculator();updateAudienceState();if(!getToken()){window.location.href='/login';return;}try{const me=await apiRequest('/me','GET');if(me.user?.role!=='parent'){window.location.href='/account';return;}sidebarUserName.textContent=me.user?.name||'Usuario';await loadFamilyMembers();await loadSavingsBoxes();showList();}catch(error){if(error.status===401){clearToken();window.location.href='/login';return;}savingsList.innerHTML='<section class="quick-card"><p class="quick-card-title">Error de sesion</p><p class="quick-card-subtitle">No se pudo validar la sesion.</p></section>';}})();
</script>
</body>
</html>
