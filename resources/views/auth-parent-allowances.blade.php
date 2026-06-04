<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mesadas (Padre)</title>
    <link rel="stylesheet" href="/css/styles.css">
    <style>
        .allowance-summary-card{cursor:pointer;}
        .allowance-summary-top{align-items:center;gap:.75rem;}
        .allowance-expand-btn{width:2.2rem;height:2.2rem;border:0;border-radius:999px;background:rgba(255,255,255,.16);color:#fff;font-size:1.1rem;line-height:1;transition:transform .18s ease,background .18s ease;}
        .allowance-summary-card:hover .allowance-expand-btn{background:rgba(255,255,255,.24);transform:rotate(180deg);}
        .allowance-alert{margin-top:.8rem;padding:.75rem .9rem;border-left:4px solid #d48b11;background:#fff3d7;color:#7a4c00;border-radius:.6rem;font-size:.92rem;font-weight:700;line-height:1.35;}
        .allowance-alert small{display:block;margin-top:.2rem;font-weight:600;opacity:.9;}
        .allowance-detail-card{background:#f8fafc;color:#10234a;}
        .allowance-detail-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:.85rem 1rem;}
        .allowance-detail-label{margin:0 0 .15rem;color:#b0891f;font-size:.8rem;font-weight:700;text-transform:uppercase;letter-spacing:.04em;}
        .allowance-detail-value{margin:0;color:#10234a;font-weight:700;word-break:break-word;}
        @media (max-width: 640px){.allowance-detail-grid{grid-template-columns:1fr;}}
    </style>
</head>
<body class="app-bg">
<main class="layout">
    <div id="sidebar-overlay" class="sidebar-overlay"></div>
    <aside id="sidebar" class="sidebar">
        <button id="sidebar-close" class="sidebar-close" type="button">x</button>
        <div class="sidebar-logo-wrap"><img class="sidebar-logo" src="/img/domus_logo.png" alt="Domus logo"></div>
        <section class="profile-card"><p id="sidebar-user-name" class="profile-name">Usuario</p><p class="profile-level">Nivel 1 - Aprendiz financiero</p></section>
        <div id="sidebar-scroll" class="sidebar-scroll">
            <nav class="sidebar-nav">
                <a class="sidebar-link" href="/account"><span class="nav-icon">&bull;</span><span>Inicio</span></a>
                <a class="sidebar-link" href="/account/users"><span class="nav-icon">&bull;</span><span>Usuarios</span></a>
                <a class="sidebar-link" href="/parent/loans"><span class="nav-icon">&bull;</span><span>Prestamos</span></a>
                <a class="sidebar-link is-active" href="/parent/allowances"><span class="nav-icon">&bull;</span><span>Mesadas</span></a>
                <a class="sidebar-link" href="/parent/tasks"><span class="nav-icon">&bull;</span><span>Tareas</span></a>
                <a class="sidebar-link" href="/account/education"><span class="nav-icon">&bull;</span><span>Educacion</span></a>
            </nav>
            <div id="scroll-hint" class="scroll-hint">Desliza para ver mas</div>
        </div>
    </aside>

    <section class="page content card">
        <div class="top-row"><button id="menu-btn" class="btn blue-btn menu-toggle" type="button">Menu</button><button id="logout-btn" class="btn btn-inline gold-btn right" type="button">Cerrar sesion</button></div>
        <p class="brand mt-2">DOMUS</p>
        <h1>Mesadas</h1>
        <p class="subtitle">Define pagos periodicos para tus miembros y ejecutalos cuando quieras.</p>
        <p id="parent-balance-note" class="legal-note mt-1"></p>

        <div class="top-row mt-1">
            <button id="allowances-tab-list" class="btn blue-btn btn-inline" type="button">Mis mesadas</button>
            <button id="allowances-tab-create" class="btn gold-btn btn-inline" type="button">Crear mesada</button>
        </div>

        <section id="allowances-list-panel" class="mt-2"><div id="allowances-list"></div></section>

        <section id="allowances-members-panel" class="mt-2 is-hidden">
            <h3 class="quick-card-title">Selecciona a quien darle mesada</h3>
            <p id="allowances-empty-message" class="legal-note is-hidden">Crea un usuario para poder dar mesadas.</p>
            <div id="allowances-members-list" class="menu-links"></div>
        </section>

        <section id="allowances-form-panel" class="mt-2 is-hidden">
            <div class="quick-card"><p class="quick-card-title" id="selected-member-label-title">Selecciona un miembro</p></div>
            <form id="allowance-form" class="form">
                <div class="field">
                    <label for="allowance-amount">Monto (pesos)</label>
                    <input id="allowance-amount" class="input" type="text" inputmode="decimal" placeholder="14.12" required>
                    <p class="choice-helper">Ejemplo: <strong>200</strong> = <strong>$200.00</strong> | <strong>14.12</strong> = <strong>$14.12</strong></p>
                </div>
                <div class="field">
                    <label for="allowance-frequency">Frecuencia</label>
                    <select id="allowance-frequency" class="input">
                        <option value="daily">Diaria</option>
                        <option value="weekly" selected>Semanal</option>
                        <option value="monthly">Mensual</option>
                        <option value="ten_seconds">Cada 10 segundos</option>
                    </select>
                </div>
                <div class="field">
                    <label class="legal-check"><input id="allowance-immediate" type="checkbox" checked> Dar primer mesada inmediatamente</label>
                </div>
                <div id="allowance-start-field" class="field is-hidden">
                    <label for="allowance-start-at">Fecha de inicio</label>
                    <input id="allowance-start-at" class="input" type="date">
                </div>
                <div id="allowance-feedback" class="feedback-box is-hidden"></div>
                <div class="top-row mt-2"><button id="allowance-back" class="btn btn-inline blue-btn" type="button">Volver</button><button id="allowance-submit" class="btn btn-inline gold-btn" type="submit">Crear mesada</button></div>
            </form>
        </section>
    </section>
</main>

<template id="template-allowance-details">
    <section id="allowance-detail-panel" class="quick-card allowance-detail-card mt-1 is-hidden">
        <div class="top-row"><p class="quick-card-title">Detalle de la mesada</p><button id="allowance-detail-back" class="btn btn-inline blue-btn" type="button">Volver</button></div>
        <div class="allowance-detail-grid mt-1">
            <div><p class="allowance-detail-label">Monto</p><p data-field="amount" class="allowance-detail-value"></p></div>
            <div><p class="allowance-detail-label">Beneficiario</p><p data-field="child" class="allowance-detail-value"></p></div>
            <div><p class="allowance-detail-label">Frecuencia</p><p data-field="frequency" class="allowance-detail-value"></p></div>
            <div><p class="allowance-detail-label">Estado</p><p data-field="status" class="allowance-detail-value"></p></div>
            <div><p class="allowance-detail-label">Inicio</p><p data-field="start_at" class="allowance-detail-value"></p></div>
            <div><p class="allowance-detail-label">Proxima ejecucion</p><p data-field="next_run_at" class="allowance-detail-value"></p></div>
            <div><p class="allowance-detail-label">Primer pago</p><p data-field="first_payment_immediate" class="allowance-detail-value"></p></div>
            <div><p class="allowance-detail-label">Ultima ejecucion</p><p data-field="last_executed_at" class="allowance-detail-value"></p></div>
        </div>
    </section>
</template>

<script>
const API_BASE='/api',TOKEN_KEY='parent_auth_token';
let currentParentBalance=0,allowanceTargets=[],selectedMemberId=null,currentAllowanceDetailPanel=null,allowancesById=new Map();
const logoutButton=document.getElementById('logout-btn'),menuButton=document.getElementById('menu-btn'),sidebar=document.getElementById('sidebar'),sidebarUserName=document.getElementById('sidebar-user-name'),sidebarClose=document.getElementById('sidebar-close'),sidebarOverlay=document.getElementById('sidebar-overlay'),sidebarScroll=document.getElementById('sidebar-scroll'),scrollHint=document.getElementById('scroll-hint');
const allowancesTabList=document.getElementById('allowances-tab-list'),allowancesTabCreate=document.getElementById('allowances-tab-create'),allowancesListPanel=document.getElementById('allowances-list-panel'),allowancesList=document.getElementById('allowances-list'),membersPanel=document.getElementById('allowances-members-panel'),membersList=document.getElementById('allowances-members-list'),emptyMessage=document.getElementById('allowances-empty-message'),formPanel=document.getElementById('allowances-form-panel'),selectedMemberLabel=document.getElementById('selected-member-label-title'),allowanceForm=document.getElementById('allowance-form'),allowanceBack=document.getElementById('allowance-back'),allowanceFeedback=document.getElementById('allowance-feedback'),allowanceSubmit=document.getElementById('allowance-submit'),parentBalanceNote=document.getElementById('parent-balance-note');
const allowanceAmount=document.getElementById('allowance-amount'),allowanceFrequency=document.getElementById('allowance-frequency'),allowanceImmediate=document.getElementById('allowance-immediate'),allowanceStartField=document.getElementById('allowance-start-field'),allowanceStartAt=document.getElementById('allowance-start-at');
function getToken(){return localStorage.getItem(TOKEN_KEY);}
function clearToken(){localStorage.removeItem(TOKEN_KEY);}
function openSidebar(){sidebar.classList.add('is-open');sidebarOverlay.classList.add('is-open');}
function closeSidebar(){sidebar.classList.remove('is-open');sidebarOverlay.classList.remove('is-open');}
function updateScrollHint(){const canScroll=sidebarScroll.scrollHeight>sidebarScroll.clientHeight;const nearBottom=sidebarScroll.scrollTop+sidebarScroll.clientHeight>=sidebarScroll.scrollHeight-4;scrollHint.classList.toggle('is-visible',canScroll&&!nearBottom);}
function escapeHtml(value){return String(value??'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#39;');}
function toMoneyFromCents(value){const amount=Number(value||0)/100;return '$'+amount.toLocaleString('en-US',{minimumFractionDigits:2,maximumFractionDigits:2});}
function formatDate(value){if(!value){return '-';}const date=new Date(value);if(Number.isNaN(date.getTime())){return String(value);}return date.toLocaleDateString('es-MX');}
function formatDateTime(value){if(!value){return '-';}const date=new Date(value);if(Number.isNaN(date.getTime())){return String(value);}return date.toLocaleString('es-MX');}
function getStatusLabel(status){const labels={pending:'Mesada pendiente',active:'Mesada activa',paused:'Mesada pausada'};return labels[String(status||'').toLowerCase()]||'Estado no definido';}
function getFrequencyLabel(frequency){const labels={daily:'Diaria',weekly:'Semanal',monthly:'Mensual',ten_seconds:'Cada 10 segundos'};return labels[String(frequency||'').toLowerCase()]||'Frecuencia no definida';}
function getLatestFailureReason(allowance){return allowance.latest_payment?.failure_reason||allowance.latestPayment?.failure_reason||allowance.payment?.failure_reason||'';}
function getAllowanceAlert(allowance){const reason=String(getLatestFailureReason(allowance)||'').toLowerCase();if(allowance.status!=='paused'){return '';}if(reason.includes('fondos')||reason.includes('saldo')){return '<div class="allowance-alert">Fondos insuficientes. Se pauso la mesada.<small>Ingresa mas dinero y vuelve a intentar el pago.</small></div>';}return '<div class="allowance-alert">Mesada pausada.<small>Revisa el detalle para ver el motivo.</small></div>';}
function parseMoneyToCents(value){const normalized=String(value??'').trim().replace(',','.');if(!/^\d+(?:\.\d{1,2})?$/.test(normalized)){return null;}const parts=normalized.split('.');const whole=Number(parts[0]||0);const fraction=String(parts[1]||'').padEnd(2,'0').slice(0,2);return (whole*100)+Number(fraction);}
function setFeedback(message,type){allowanceFeedback.textContent=message;allowanceFeedback.className='feedback-box '+type;}
function clearFeedback(){allowanceFeedback.textContent='';allowanceFeedback.className='feedback-box is-hidden';}
async function apiRequest(path,method,payload){const token=getToken();const headers={Accept:'application/json','Content-Type':'application/json'};if(token){headers.Authorization='Bearer '+token;}const response=await fetch(API_BASE+path,{method,headers,body:payload?JSON.stringify(payload):undefined});const data=await response.json().catch(()=>({}));if(!response.ok){throw {status:response.status,data};}return data;}
function updateBalanceNote(){parentBalanceNote.textContent='* Cuentas con '+toMoneyFromCents(currentParentBalance)+' para dar mesadas';}
function showList(){closeAllowanceDetail();allowancesListPanel.classList.remove('is-hidden');membersPanel.classList.add('is-hidden');formPanel.classList.add('is-hidden');allowancesTabList.classList.remove('gold-btn');allowancesTabList.classList.add('blue-btn');allowancesTabCreate.classList.remove('blue-btn');allowancesTabCreate.classList.add('gold-btn');}
function showCreate(){closeAllowanceDetail();allowancesListPanel.classList.add('is-hidden');membersPanel.classList.remove('is-hidden');formPanel.classList.add('is-hidden');allowancesTabCreate.classList.remove('gold-btn');allowancesTabCreate.classList.add('blue-btn');allowancesTabList.classList.remove('blue-btn');allowancesTabList.classList.add('gold-btn');renderMembersForAllowance();}
function openAllowanceForm(target){selectedMemberId=target.user_id;selectedMemberLabel.textContent=target.name+' (@'+(target.username||'sin_username')+')';membersPanel.classList.add('is-hidden');formPanel.classList.remove('is-hidden');clearFeedback();}
function renderMembersForAllowance(){if(allowanceTargets.length===0){emptyMessage.classList.remove('is-hidden');membersList.innerHTML='';return;}emptyMessage.classList.add('is-hidden');membersList.innerHTML=allowanceTargets.map(target=>'<button class="btn blue-btn" type="button" data-member-id="'+Number(target.user_id)+'">'+escapeHtml(target.name)+' (@'+escapeHtml(target.username||'sin_username')+')</button>').join('');membersList.querySelectorAll('button[data-member-id]').forEach(button=>button.addEventListener('click',()=>{const target=allowanceTargets.find(item=>String(item.user_id)===button.dataset.memberId);if(target){openAllowanceForm(target);}}));}
function closeAllowanceDetail(){if(currentAllowanceDetailPanel){currentAllowanceDetailPanel.remove();currentAllowanceDetailPanel=null;}allowancesListPanel.classList.remove('is-hidden');}
function renderAllowanceDetail(allowance){closeAllowanceDetail();const template=document.getElementById('template-allowance-details');currentAllowanceDetailPanel=template.content.firstElementChild.cloneNode(true);currentAllowanceDetailPanel.classList.remove('is-hidden');currentAllowanceDetailPanel.querySelector('#allowance-detail-back').addEventListener('click',closeAllowanceDetail);currentAllowanceDetailPanel.querySelector('[data-field="amount"]').textContent=toMoneyFromCents(allowance.amount_cents);currentAllowanceDetailPanel.querySelector('[data-field="child"]').textContent=allowance.child?.name?allowance.child.name+' (@'+(allowance.child.username||'sin_username')+')':'-';currentAllowanceDetailPanel.querySelector('[data-field="frequency"]').textContent=getFrequencyLabel(allowance.frequency);currentAllowanceDetailPanel.querySelector('[data-field="status"]').textContent=getStatusLabel(allowance.status);currentAllowanceDetailPanel.querySelector('[data-field="start_at"]').textContent=formatDate(allowance.start_at);currentAllowanceDetailPanel.querySelector('[data-field="next_run_at"]').textContent=formatDateTime(allowance.next_run_at);currentAllowanceDetailPanel.querySelector('[data-field="first_payment_immediate"]').textContent=allowance.first_payment_immediate?'Si':'No';currentAllowanceDetailPanel.querySelector('[data-field="last_executed_at"]').textContent=formatDateTime(allowance.last_executed_at);allowancesListPanel.after(currentAllowanceDetailPanel);allowancesListPanel.classList.add('is-hidden');currentAllowanceDetailPanel.scrollIntoView({behavior:'smooth',block:'start'});}
function renderAllowanceCard(allowance){const failureReason=String(getLatestFailureReason(allowance)||'').toLowerCase();const hasFundsAlert=allowance.status==='paused'&&(failureReason.includes('fondos')||failureReason.includes('saldo'));const canExecute=allowance.status!=='paused'||hasFundsAlert;const executeLabel=allowance.status==='paused'&&hasFundsAlert?'Reintentar':'Ejecutar ahora';const executeButton=canExecute?'<button class="btn gold-btn btn-inline execute-allowance-btn" type="button" data-allowance-id="'+Number(allowance.id)+'">'+executeLabel+'</button>':'';return '<section class="quick-card mt-1 allowance-summary-card" data-allowance-id="'+Number(allowance.id)+'"><div class="top-row allowance-summary-top"><div><p class="quick-card-title">'+escapeHtml(allowance.child?.name||'Miembro')+'</p><p class="quick-card-subtitle">'+toMoneyFromCents(allowance.amount_cents)+' | '+escapeHtml(getFrequencyLabel(allowance.frequency))+' | '+escapeHtml(getStatusLabel(allowance.status))+'</p></div><button class="allowance-expand-btn" type="button" aria-label="Ver detalle">v</button></div><p class="quick-card-subtitle">Inicio: '+escapeHtml(formatDate(allowance.start_at))+' | Proxima ejecucion: '+escapeHtml(formatDateTime(allowance.next_run_at)||'-')+'</p>'+getAllowanceAlert(allowance)+executeButton+'</section>';}
function bindAllowanceCards(){allowancesList.querySelectorAll('.allowance-summary-card').forEach(card=>card.addEventListener('click',()=>{const allowance=allowancesById.get(Number(card.dataset.allowanceId));if(allowance){renderAllowanceDetail(allowance);}}));allowancesList.querySelectorAll('.execute-allowance-btn').forEach(button=>button.addEventListener('click',event=>{event.stopPropagation();button.disabled=true;executeAllowance(Number(button.dataset.allowanceId)).finally(()=>{button.disabled=false;});}));}
async function loadFamilyMembers(){const response=await apiRequest('/family-members','GET');allowanceTargets=response.loan_targets||[];}
async function loadAllowances(){try{const response=await apiRequest('/allowances','GET');const allowances=response.allowances||[];allowancesById=new Map(allowances.map(item=>[Number(item.id),item]));if(allowances.length===0){allowancesList.innerHTML='<section class="quick-card"><p class="quick-card-title">Sin mesadas</p><p class="quick-card-subtitle">Todavia no has creado mesadas.</p><button id="empty-create-allowance-btn" class="btn gold-btn btn-inline mt-1" type="button">Crear mesada</button></section>';document.getElementById('empty-create-allowance-btn').addEventListener('click',showCreate);return;}allowancesList.innerHTML=allowances.map(renderAllowanceCard).join('');bindAllowanceCards();}catch(error){allowancesList.innerHTML='<section class="quick-card"><p class="quick-card-title">Error</p><p class="quick-card-subtitle">'+escapeHtml(error?.data?.message||'No se pudieron cargar mesadas.')+'</p></section>';}}
async function executeAllowance(allowanceId){try{const response=await apiRequest('/allowances/'+allowanceId+'/execute','POST');if(Number.isFinite(Number(response.remaining_parent_balance))){currentParentBalance=Number(response.remaining_parent_balance);updateBalanceNote();}setFeedback(response.executed===false?(response.message||'No se pudo ejecutar la mesada.'):(response.message||'Mesada ejecutada correctamente.'),response.executed===false?'feedback-error':'feedback-success');await loadAllowances();}catch(error){setFeedback(error?.data?.message||'No se pudo ejecutar la mesada.','feedback-error');}}
allowanceImmediate.addEventListener('change',()=>{allowanceStartField.classList.toggle('is-hidden',allowanceImmediate.checked);allowanceStartAt.required=!allowanceImmediate.checked;});
allowanceForm.addEventListener('submit',async event=>{event.preventDefault();clearFeedback();if(!selectedMemberId){setFeedback('Selecciona un miembro antes de crear la mesada.','feedback-error');return;}const amountCents=parseMoneyToCents(allowanceAmount.value);if(amountCents===null||amountCents<1){setFeedback('Escribe un monto valido con hasta 2 decimales.','feedback-error');return;}if(!allowanceImmediate.checked&&!allowanceStartAt.value){setFeedback('Elige una fecha de inicio para la primera mesada.','feedback-error');return;}const payload={child_user_id:selectedMemberId,amount:allowanceAmount.value.trim(),frequency:allowanceFrequency.value,first_payment_immediate:allowanceImmediate.checked,start_at:allowanceImmediate.checked?null:allowanceStartAt.value};try{const response=await apiRequest('/allowances','POST',payload);if(Number.isFinite(Number(response?.result?.remaining_parent_balance))){currentParentBalance=Number(response.result.remaining_parent_balance);updateBalanceNote();}allowanceForm.reset();allowanceImmediate.checked=true;allowanceStartField.classList.add('is-hidden');allowanceStartAt.required=false;setFeedback(response.result?.executed===false?(response.message||'Mesada guardada como pendiente.'): (response.message||'Mesada guardada correctamente.'),response.result?.executed===false?'feedback-error':'feedback-success');await loadAllowances();}catch(error){setFeedback(error?.data?.message||'No se pudo crear la mesada.','feedback-error');}});
allowanceBack.addEventListener('click',showCreate);
allowancesTabList.addEventListener('click',showList);
allowancesTabCreate.addEventListener('click',showCreate);
logoutButton.addEventListener('click',async()=>{try{await apiRequest('/logout','POST');}catch(error){}finally{clearToken();window.location.href='/login';}});
menuButton.addEventListener('click',()=>{if(sidebar.classList.contains('is-open')){closeSidebar();return;}openSidebar();updateScrollHint();});
sidebarClose.addEventListener('click',closeSidebar);
sidebarOverlay.addEventListener('click',closeSidebar);
sidebarScroll.addEventListener('scroll',updateScrollHint);
window.addEventListener('resize',()=>{if(window.innerWidth>=768){closeSidebar();}updateScrollHint();});
(async function bootstrap(){updateScrollHint();if(!getToken()){window.location.href='/login';return;}try{const me=await apiRequest('/me','GET');if(me.user?.role!=='parent'){window.location.href='/account';return;}sidebarUserName.textContent=me.user?.name||'Usuario';currentParentBalance=Number(me.user?.balance_cents||0);updateBalanceNote();await loadFamilyMembers();await loadAllowances();showList();}catch(error){if(error.status===401){clearToken();window.location.href='/login';return;}allowancesList.innerHTML='<section class="quick-card"><p class="quick-card-title">Error de sesion</p><p class="quick-card-subtitle">No se pudo validar la sesion.</p></section>';}})();
</script>
</body>
</html>
