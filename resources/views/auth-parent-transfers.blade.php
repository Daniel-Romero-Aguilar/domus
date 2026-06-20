<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dar dinero</title>
    <link rel="stylesheet" href="/css/styles.css">
    <style>
        .transfer-summary-card{cursor:pointer;}
        .transfer-summary-top{align-items:center;gap:.75rem;}
        .transfer-expand-btn{width:2.2rem;height:2.2rem;border:0;border-radius:999px;background:rgba(255,255,255,.16);color:#fff;font-size:1.1rem;line-height:1;transition:transform .18s ease,background .18s ease;}
        .transfer-summary-card:hover .transfer-expand-btn{background:rgba(255,255,255,.24);transform:rotate(180deg);}
        .transfer-alert{margin-top:.8rem;padding:.75rem .9rem;border-left:4px solid #d48b11;background:#fff3d7;color:#7a4c00;border-radius:.6rem;font-size:.92rem;font-weight:700;line-height:1.35;}
        .transfer-alert small{display:block;margin-top:.2rem;font-weight:600;opacity:.9;}
        .transfer-detail-card{background:#f8fafc;color:#10234a;}
        .transfer-detail-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:.85rem 1rem;}
        .transfer-detail-label{margin:0 0 .15rem;color:#b0891f;font-size:.8rem;font-weight:700;text-transform:uppercase;letter-spacing:.04em;}
        .transfer-detail-value{margin:0;color:#10234a;font-weight:700;word-break:break-word;}
        @media (max-width: 640px){.transfer-detail-grid{grid-template-columns:1fr;}}
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
                <a class="sidebar-link is-active" href="/parent/transfers"><span class="nav-icon">&bull;</span><span>Dar dinero</span></a>
                <a class="sidebar-link" href="/parent/allowances"><span class="nav-icon">&bull;</span><span>Mesadas</span></a>
                <a class="sidebar-link" href="/parent/savings-boxes"><span class="nav-icon">&bull;</span><span>Cajas de ahorro</span></a>
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
        <h1>Dar dinero</h1>
        <p class="subtitle">Envía dinero directo a un miembro de tu familia, sin intereses ni prestamos.</p>
        <p id="parent-balance-note" class="legal-note mt-1"></p>

        <div class="top-row mt-1">
            <button id="transfers-tab-list" class="btn blue-btn btn-inline" type="button">Mis envios</button>
            <button id="transfers-tab-create" class="btn gold-btn btn-inline" type="button">Dar dinero</button>
        </div>

        <section id="transfers-list-panel" class="mt-2"><div id="transfers-list"></div></section>

        <section id="transfers-members-panel" class="mt-2 is-hidden">
            <h3 class="quick-card-title">Selecciona a quien enviar dinero</h3>
            <p id="transfers-empty-message" class="legal-note is-hidden">Crea un usuario para poder enviar dinero.</p>
            <div id="transfers-members-list" class="menu-links"></div>
        </section>

        <section id="transfers-form-panel" class="mt-2 is-hidden">
            <div class="quick-card"><p class="quick-card-title" id="selected-member-label-title">Selecciona un miembro</p></div>
            <form id="transfer-form" class="form">
                <div class="field">
                    <label for="transfer-amount">Monto (pesos)</label>
                    <input id="transfer-amount" class="input" type="text" inputmode="decimal" placeholder="14.12" required>
                    <p class="choice-helper">Ejemplo: <strong>100</strong> = <strong>$100.00</strong></p>
                </div>
                <input id="transfer-idempotency-key" type="hidden">
                <div id="transfer-feedback" class="feedback-box is-hidden"></div>
                <div class="top-row mt-2"><button id="transfer-back" class="btn btn-inline blue-btn" type="button">Volver</button><button id="transfer-submit" class="btn btn-inline gold-btn" type="submit">Dar dinero</button></div>
            </form>
        </section>
    </section>
</main>

<template id="template-transfer-details">
    <section id="transfer-detail-panel" class="quick-card transfer-detail-card mt-1 is-hidden">
        <div class="top-row"><p class="quick-card-title">Detalle del envio</p><button id="transfer-detail-back" class="btn btn-inline blue-btn" type="button">Volver</button></div>
        <div class="transfer-detail-grid mt-1">
            <div><p class="transfer-detail-label">Monto</p><p data-field="amount" class="transfer-detail-value"></p></div>
            <div><p class="transfer-detail-label">Destinatario</p><p data-field="child" class="transfer-detail-value"></p></div>
            <div><p class="transfer-detail-label">Estado</p><p data-field="status" class="transfer-detail-value"></p></div>
            <div><p class="transfer-detail-label">Fecha</p><p data-field="executed_at" class="transfer-detail-value"></p></div>
            <div><p class="transfer-detail-label">Registro antes</p><p data-field="parent_before" class="transfer-detail-value"></p></div>
            <div><p class="transfer-detail-label">Registro despues</p><p data-field="parent_after" class="transfer-detail-value"></p></div>
        </div>
        <div data-field="alert"></div>
    </section>
</template>

<script>
const API_BASE='/api',TOKEN_KEY='parent_auth_token';
let currentParentBalance=0,transferTargets=[],selectedMemberId=null,currentTransferDetailPanel=null,transfersById=new Map();
const logoutButton=document.getElementById('logout-btn'),menuButton=document.getElementById('menu-btn'),sidebar=document.getElementById('sidebar'),sidebarUserName=document.getElementById('sidebar-user-name'),sidebarClose=document.getElementById('sidebar-close'),sidebarOverlay=document.getElementById('sidebar-overlay'),sidebarScroll=document.getElementById('sidebar-scroll'),scrollHint=document.getElementById('scroll-hint');
const transfersTabList=document.getElementById('transfers-tab-list'),transfersTabCreate=document.getElementById('transfers-tab-create'),transfersListPanel=document.getElementById('transfers-list-panel'),transfersList=document.getElementById('transfers-list'),membersPanel=document.getElementById('transfers-members-panel'),membersList=document.getElementById('transfers-members-list'),emptyMessage=document.getElementById('transfers-empty-message'),formPanel=document.getElementById('transfers-form-panel'),selectedMemberLabel=document.getElementById('selected-member-label-title'),transferForm=document.getElementById('transfer-form'),transferBack=document.getElementById('transfer-back'),transferFeedback=document.getElementById('transfer-feedback'),transferSubmit=document.getElementById('transfer-submit'),parentBalanceNote=document.getElementById('parent-balance-note');
const transferAmount=document.getElementById('transfer-amount'),transferIdempotencyInput=document.getElementById('transfer-idempotency-key');
function getToken(){return localStorage.getItem(TOKEN_KEY);}
function clearToken(){localStorage.removeItem(TOKEN_KEY);}
function openSidebar(){sidebar.classList.add('is-open');sidebarOverlay.classList.add('is-open');}
function closeSidebar(){sidebar.classList.remove('is-open');sidebarOverlay.classList.remove('is-open');}
function updateScrollHint(){const canScroll=sidebarScroll.scrollHeight>sidebarScroll.clientHeight;const nearBottom=sidebarScroll.scrollTop+sidebarScroll.clientHeight>=sidebarScroll.scrollHeight-4;scrollHint.classList.toggle('is-visible',canScroll&&!nearBottom);}
function escapeHtml(value){return String(value??'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#39;');}
function toMoneyFromCents(value){const amount=Number(value||0)/100;return '$'+amount.toLocaleString('en-US',{minimumFractionDigits:2,maximumFractionDigits:2});}
function formatDateTime(value){if(!value){return '-';}const date=new Date(value);if(Number.isNaN(date.getTime())){return String(value);}return date.toLocaleString('es-MX');}
function getStatusLabel(status){const labels={completed:'Dinero enviado',failed:'Envio fallido',processing:'Procesando'};return labels[String(status||'').toLowerCase()]||'Estado no definido';}
function getLatestFailureReason(transfer){return transfer.failure_reason||'';}
function getTransferAlert(transfer){const reason=String(getLatestFailureReason(transfer)||'').toLowerCase();if(transfer.status!=='failed'){return '';}if(reason.includes('fondos')||reason.includes('saldo')){return '<div class="transfer-alert">Este envio fallo con una regla anterior de saldo.<small>Intenta enviarlo de nuevo; el padre ya no necesita saldo disponible.</small></div>';}return '<div class="transfer-alert">No se pudo enviar el dinero.<small>Revisa el detalle para ver el motivo.</small></div>';}
function parseMoneyToCents(value){const normalized=String(value??'').trim().replace(',','.');if(!/^\d+(?:\.\d{1,2})?$/.test(normalized)){return null;}const parts=normalized.split('.');const whole=Number(parts[0]||0);const fraction=String(parts[1]||'').padEnd(2,'0').slice(0,2);return (whole*100)+Number(fraction);}
function generateIdempotencyKey(){return (window.crypto&&crypto.randomUUID)?crypto.randomUUID():'key-'+Date.now()+'-'+Math.random().toString(16).slice(2);}
function setFeedback(message,type){transferFeedback.textContent=message;transferFeedback.className='feedback-box '+type;}
function clearFeedback(){transferFeedback.textContent='';transferFeedback.className='feedback-box is-hidden';}
async function apiRequest(path,method,payload,extraHeaders={}){const token=getToken();const headers={Accept:'application/json','Content-Type':'application/json',...extraHeaders};if(token){headers.Authorization='Bearer '+token;}const response=await fetch(API_BASE+path,{method,headers,body:payload?JSON.stringify(payload):undefined});const data=await response.json().catch(()=>({}));if(!response.ok){throw {status:response.status,data};}return data;}
function updateBalanceNote(){parentBalanceNote.textContent='Dinero entregado registrado: '+toMoneyFromCents(currentParentBalance);}
function showList(){closeTransferDetail();transfersListPanel.classList.remove('is-hidden');membersPanel.classList.add('is-hidden');formPanel.classList.add('is-hidden');transfersTabList.classList.remove('gold-btn');transfersTabList.classList.add('blue-btn');transfersTabCreate.classList.remove('blue-btn');transfersTabCreate.classList.add('gold-btn');}
function showCreate(){closeTransferDetail();transfersListPanel.classList.add('is-hidden');membersPanel.classList.remove('is-hidden');formPanel.classList.add('is-hidden');transfersTabCreate.classList.remove('gold-btn');transfersTabCreate.classList.add('blue-btn');transfersTabList.classList.remove('blue-btn');transfersTabList.classList.add('gold-btn');renderMembersForTransfer();}
function openTransferForm(target){selectedMemberId=target.user_id;selectedMemberLabel.textContent=target.name+' (@'+(target.username||'sin_username')+')';transferIdempotencyInput.value=generateIdempotencyKey();membersPanel.classList.add('is-hidden');formPanel.classList.remove('is-hidden');clearFeedback();}
function renderMembersForTransfer(){if(transferTargets.length===0){emptyMessage.classList.remove('is-hidden');membersList.innerHTML='';return;}emptyMessage.classList.add('is-hidden');membersList.innerHTML=transferTargets.map(target=>'<button class="btn blue-btn" type="button" data-member-id="'+Number(target.user_id)+'">'+escapeHtml(target.name)+' (@'+escapeHtml(target.username||'sin_username')+')</button>').join('');membersList.querySelectorAll('button[data-member-id]').forEach(button=>button.addEventListener('click',()=>{const target=transferTargets.find(item=>String(item.user_id)===button.dataset.memberId);if(target){openTransferForm(target);}}));}
function closeTransferDetail(){if(currentTransferDetailPanel){currentTransferDetailPanel.remove();currentTransferDetailPanel=null;}transfersListPanel.classList.remove('is-hidden');}
function renderTransferDetail(transfer){closeTransferDetail();const template=document.getElementById('template-transfer-details');currentTransferDetailPanel=template.content.firstElementChild.cloneNode(true);currentTransferDetailPanel.classList.remove('is-hidden');currentTransferDetailPanel.querySelector('#transfer-detail-back').addEventListener('click',closeTransferDetail);currentTransferDetailPanel.querySelector('[data-field="amount"]').textContent=toMoneyFromCents(transfer.amount_cents);currentTransferDetailPanel.querySelector('[data-field="child"]').textContent=transfer.child?.name?transfer.child.name+' (@'+(transfer.child.username||'sin_username')+')':'-';currentTransferDetailPanel.querySelector('[data-field="status"]').textContent=getStatusLabel(transfer.status);currentTransferDetailPanel.querySelector('[data-field="executed_at"]').textContent=formatDateTime(transfer.executed_at);currentTransferDetailPanel.querySelector('[data-field="parent_before"]').textContent=toMoneyFromCents(transfer.parent_balance_before);currentTransferDetailPanel.querySelector('[data-field="parent_after"]').textContent=toMoneyFromCents(transfer.parent_balance_after);currentTransferDetailPanel.querySelector('[data-field="alert"]').innerHTML=getTransferAlert(transfer);transfersListPanel.after(currentTransferDetailPanel);transfersListPanel.classList.add('is-hidden');currentTransferDetailPanel.scrollIntoView({behavior:'smooth',block:'start'});}
function renderTransferCard(transfer){const cardAlert=getTransferAlert(transfer);return '<section class="quick-card mt-1 transfer-summary-card" data-transfer-id="'+Number(transfer.id)+'"><div class="top-row transfer-summary-top"><div><p class="quick-card-title">'+escapeHtml(transfer.child?.name||'Miembro')+'</p><p class="quick-card-subtitle">'+toMoneyFromCents(transfer.amount_cents)+' | '+escapeHtml(getStatusLabel(transfer.status))+'</p></div><button class="transfer-expand-btn" type="button" aria-label="Ver detalle">v</button></div><p class="quick-card-subtitle">Fecha: '+escapeHtml(formatDateTime(transfer.executed_at))+'</p>'+cardAlert+'</section>';}
function bindTransferCards(){transfersList.querySelectorAll('.transfer-summary-card').forEach(card=>card.addEventListener('click',()=>{const transfer=transfersById.get(Number(card.dataset.transferId));if(transfer){renderTransferDetail(transfer);}}));}
async function loadFamilyMembers(){const response=await apiRequest('/family-members','GET');transferTargets=response.loan_targets||[];}
async function loadTransfers(){try{const response=await apiRequest('/transfers','GET');const transfers=response.transfers||[];transfersById=new Map(transfers.map(item=>[Number(item.id),item]));if(transfers.length===0){transfersList.innerHTML='<section class="quick-card"><p class="quick-card-title">Sin envios</p><p class="quick-card-subtitle">Todavia no has enviado dinero directo.</p><button id="empty-create-transfer-btn" class="btn gold-btn btn-inline mt-1" type="button">Dar dinero</button></section>';document.getElementById('empty-create-transfer-btn').addEventListener('click',showCreate);return;}transfersList.innerHTML=transfers.map(renderTransferCard).join('');bindTransferCards();}catch(error){transfersList.innerHTML='<section class="quick-card"><p class="quick-card-title">Error</p><p class="quick-card-subtitle">'+escapeHtml(error?.data?.message||'No se pudieron cargar los envios.')+'</p></section>';}}
async function submitTransfer(){const amountCents=parseMoneyToCents(transferAmount.value);if(amountCents===null||amountCents<1){setFeedback('Escribe un monto valido con hasta 2 decimales.','feedback-error');return;}if(!selectedMemberId){setFeedback('Selecciona un miembro antes de enviar dinero.','feedback-error');return;}transferSubmit.disabled=true;try{const response=await apiRequest('/transfers','POST',{child_user_id:selectedMemberId,amount:transferAmount.value.trim()},{'Idempotency-Key':transferIdempotencyInput.value});if(Number.isFinite(Number(response.remaining_parent_balance))){currentParentBalance=Number(response.remaining_parent_balance);updateBalanceNote();}setFeedback(response.message||'Dinero enviado correctamente.',response.executed===false?'feedback-error':'feedback-success');transferAmount.value='';transferIdempotencyInput.value=generateIdempotencyKey();await loadTransfers();}catch(error){setFeedback(error?.data?.message||'No se pudo enviar el dinero.','feedback-error');transferIdempotencyInput.value=generateIdempotencyKey();}finally{transferSubmit.disabled=false;}}
transferForm.addEventListener('submit',async event=>{event.preventDefault();clearFeedback();await submitTransfer();});
transferBack.addEventListener('click',showCreate);
transfersTabList.addEventListener('click',showList);
transfersTabCreate.addEventListener('click',showCreate);
logoutButton.addEventListener('click',async()=>{try{await apiRequest('/logout','POST');}catch(error){}finally{clearToken();window.location.href='/login';}});
menuButton.addEventListener('click',()=>{if(sidebar.classList.contains('is-open')){closeSidebar();return;}openSidebar();updateScrollHint();});
sidebarClose.addEventListener('click',closeSidebar);
sidebarOverlay.addEventListener('click',closeSidebar);
sidebarScroll.addEventListener('scroll',updateScrollHint);
window.addEventListener('resize',()=>{if(window.innerWidth>=768){closeSidebar();}updateScrollHint();});
(async function bootstrap(){updateScrollHint();if(!getToken()){window.location.href='/login';return;}try{const me=await apiRequest('/me','GET');if(me.user?.role!=='parent'){window.location.href='/account';return;}sidebarUserName.textContent=me.user?.name||'Usuario';currentParentBalance=Number(me.user?.balance_cents||0);updateBalanceNote();await loadFamilyMembers();await loadTransfers();showList();}catch(error){if(error.status===401){clearToken();window.location.href='/login';return;}transfersList.innerHTML='<section class="quick-card"><p class="quick-card-title">Error de sesion</p><p class="quick-card-subtitle">No se pudo validar la sesion.</p></section>';}})();
</script>
</body>
</html>
