<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Prestamos (Padre)</title>
    <link rel="stylesheet" href="/css/styles.css">
    <style>
        .input-invalid{border-color:#c62828!important;background:#fff4f4!important;box-shadow:0 0 0 1px rgba(198,40,40,.15) inset;}
        .loan-summary-card{cursor:pointer;}
        .loan-summary-top{align-items:center;gap:.75rem;}
        .loan-expand-btn{width:2.2rem;height:2.2rem;border:0;border-radius:999px;background:rgba(255,255,255,.16);color:#fff;font-size:1.1rem;line-height:1;transition:transform .18s ease,background .18s ease;}
        .loan-summary-card:hover .loan-expand-btn{background:rgba(255,255,255,.24);transform:rotate(180deg);}
        .loan-detail-card{background:#f8fafc;color:#10234a;}
        .loan-detail-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:.85rem 1rem;}
        .loan-detail-label{margin:0 0 .15rem;color:#b0891f;font-size:.8rem;font-weight:700;text-transform:uppercase;letter-spacing:.04em;}
        .loan-detail-value{margin:0;color:#10234a;font-weight:700;word-break:break-word;}
        @media (max-width: 640px){.loan-detail-grid{grid-template-columns:1fr;}}
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
                <a class="sidebar-link is-active" href="/parent/loans"><span class="nav-icon">&bull;</span><span>Prestamos</span></a>
                <a class="sidebar-link" href="/parent/allowances"><span class="nav-icon">&bull;</span><span>Mesadas</span></a>
                <a class="sidebar-link" href="/parent/tasks"><span class="nav-icon">&bull;</span><span>Tareas</span></a>
                <a class="sidebar-link" href="/account/education"><span class="nav-icon">&bull;</span><span>Educacion</span></a>
            </nav>
            <div id="scroll-hint" class="scroll-hint">Desliza para ver mas</div>
        </div>
    </aside>

    <section class="page content card">
        <div class="top-row"><button id="menu-btn" class="btn blue-btn menu-toggle" type="button">Menu</button><button id="logout-btn" class="btn btn-inline gold-btn right" type="button">Cerrar sesion</button></div>
        <p class="brand mt-2">DOMUS</p>
        <h1>Prestamos</h1>
        <p class="subtitle">Da prestamos a tus miembros y aprueba solicitudes pendientes.</p>
        <p id="parent-balance-note" class="legal-note mt-1"></p>

        <div class="top-row mt-1">
            <button id="loans-tab-list" class="btn blue-btn btn-inline" type="button">Mis prestamos</button>
            <button id="loans-tab-create" class="btn gold-btn btn-inline" type="button">Dar prestamo</button>
        </div>

        <section id="loans-list-panel" class="mt-2"><div id="loans-list"></div></section>

        <section id="loan-members-panel" class="mt-2 is-hidden">
            <h3 class="quick-card-title">Selecciona a quien prestar</h3>
            <p id="loan-empty-message" class="legal-note is-hidden">Crea un usuario para poder dar prestamos.</p>
            <div id="loan-members-list" class="menu-links"></div>
        </section>

        <section id="loan-form-panel" class="mt-2 is-hidden">
            <div class="quick-card"><p class="quick-card-title" id="selected-member-label-title">Selecciona un miembro</p></div>
            <form id="loan-form" class="form">
                <div class="field"><label for="loan-amount">Cantidad</label><input id="loan-amount" class="input" type="number" min="1" step="1" required></div>
                <div class="field"><label for="loan-reason">Motivo</label><input id="loan-reason" class="input" type="text" maxlength="120" placeholder="Ejemplo: Laptop para escuela"></div>
                <div class="field"><label for="loan-due-date">Fecha limite de pago</label><input id="loan-due-date" class="input" type="date" required></div>
                <div class="field"><label>Tipo de pago</label><div class="choice-row"><label class="choice-pill"><input id="loan-mode-single" name="loan-mode" type="radio" value="single" checked> Pago unico</label><label class="choice-pill"><input id="loan-mode-deferred" name="loan-mode" type="radio" value="deferred"> Pago diferido</label></div></div>
                <div id="deferred-payment-fields" class="field is-hidden">
                    <label>Pagos diferidos</label>
                    <div class="choice-grid mt-1">
                        <button type="button" class="btn btn-inline blue-btn installment-btn" data-count="3">3</button>
                        <button type="button" class="btn btn-inline blue-btn installment-btn" data-count="6">6</button>
                        <button type="button" class="btn btn-inline blue-btn installment-btn" data-count="12">12</button>
                        <button type="button" class="btn btn-inline blue-btn installment-btn" data-count="18">18</button>
                    </div>
                    <input id="custom-installments" class="input mt-1" type="number" min="1" placeholder="Meses personalizados">
                    <select id="installment-frequency" class="input mt-1"><option value="monthly">Mensual</option><option value="biweekly">Quincenal</option><option value="weekly">Semanal</option></select>
                </div>
                <div class="field">
                    <label class="legal-check"><input id="with-interest" type="checkbox"> Agregar intereses</label>
                    <div id="interest-mode-row" class="choice-row mt-1 is-hidden"><label class="choice-pill"><input id="interest-mode-percent" name="interest-mode" type="radio" value="percent" checked> Porcentaje</label><label class="choice-pill"><input id="interest-mode-fixed" name="interest-mode" type="radio" value="fixed"> Monto fijo</label></div>
                    <div id="interest-options" class="choice-grid mt-1 is-hidden"><button type="button" class="btn btn-inline gold-btn interest-btn" data-rate="1">1%</button><button type="button" class="btn btn-inline gold-btn interest-btn" data-rate="5">5%</button><button type="button" class="btn btn-inline gold-btn interest-btn" data-rate="10">10%</button><button type="button" class="btn btn-inline gold-btn interest-btn" data-rate="12">12%</button></div>
                    <input id="custom-interest" class="input mt-1 is-hidden" type="number" min="0" step="0.01" placeholder="Interes personalizado">
                </div>
                <div id="loan-feedback" class="feedback-box is-hidden"></div>
                <div class="top-row mt-2"><button id="loan-back" class="btn btn-inline blue-btn" type="button">Volver</button><button id="loan-submit" class="btn btn-inline gold-btn" type="submit">Dar prestamo</button></div>
            </form>
        </section>
    </section>
</main>

<template id="template-loan-details">
    <section id="loan-detail-panel" class="quick-card loan-detail-card mt-1 is-hidden">
        <div class="top-row"><p class="quick-card-title">Detalle del prestamo</p><button id="loan-detail-back" class="btn btn-inline blue-btn" type="button">Volver</button></div>
        <div class="loan-detail-grid mt-1">
            <div><p class="loan-detail-label">Prestado</p><p data-field="amount" class="loan-detail-value"></p></div>
            <div><p class="loan-detail-label">Total a pagar</p><p data-field="total_amount" class="loan-detail-value"></p></div>
            <div><p class="loan-detail-label">Beneficiario</p><p data-field="child" class="loan-detail-value"></p></div>
            <div><p class="loan-detail-label">Motivo</p><p data-field="reason" class="loan-detail-value"></p></div>
            <div><p class="loan-detail-label">Fecha limite</p><p data-field="due_date" class="loan-detail-value"></p></div>
            <div><p class="loan-detail-label">Estado</p><p data-field="status" class="loan-detail-value"></p></div>
            <div><p class="loan-detail-label">Pagos</p><p data-field="installments" class="loan-detail-value"></p></div>
            <div><p class="loan-detail-label">Frecuencia</p><p data-field="frequency" class="loan-detail-value"></p></div>
            <div><p class="loan-detail-label">Intereses</p><p data-field="interest" class="loan-detail-value"></p></div>
            <div><p class="loan-detail-label">Creado</p><p data-field="created_at" class="loan-detail-value"></p></div>
        </div>
    </section>
</template>

<script>
const API_BASE='/api',TOKEN_KEY='parent_auth_token';
let currentParentBalance=0,loanTargets=[],selectedMemberId=null,selectedInstallments=3,selectedInterest=0,currentLoanDetailPanel=null,loansById=new Map();
const logoutButton=document.getElementById('logout-btn'),menuButton=document.getElementById('menu-btn'),sidebar=document.getElementById('sidebar'),sidebarUserName=document.getElementById('sidebar-user-name'),sidebarClose=document.getElementById('sidebar-close'),sidebarOverlay=document.getElementById('sidebar-overlay'),sidebarScroll=document.getElementById('sidebar-scroll'),scrollHint=document.getElementById('scroll-hint');
const loansTabList=document.getElementById('loans-tab-list'),loansTabCreate=document.getElementById('loans-tab-create'),loansListPanel=document.getElementById('loans-list-panel'),loansList=document.getElementById('loans-list'),membersPanel=document.getElementById('loan-members-panel'),membersList=document.getElementById('loan-members-list'),emptyMessage=document.getElementById('loan-empty-message'),formPanel=document.getElementById('loan-form-panel'),selectedMemberLabel=document.getElementById('selected-member-label-title'),loanForm=document.getElementById('loan-form'),loanBack=document.getElementById('loan-back'),loanFeedback=document.getElementById('loan-feedback'),loanSubmit=document.getElementById('loan-submit'),parentBalanceNote=document.getElementById('parent-balance-note');
const loanAmount=document.getElementById('loan-amount'),loanReason=document.getElementById('loan-reason'),loanDueDate=document.getElementById('loan-due-date'),customInstallments=document.getElementById('custom-installments'),frequency=document.getElementById('installment-frequency'),withInterest=document.getElementById('with-interest'),interestOptions=document.getElementById('interest-options'),customInterest=document.getElementById('custom-interest'),loanModeSingle=document.getElementById('loan-mode-single'),loanModeDeferred=document.getElementById('loan-mode-deferred'),deferredPaymentFields=document.getElementById('deferred-payment-fields'),interestModeRow=document.getElementById('interest-mode-row'),interestModeFixed=document.getElementById('interest-mode-fixed');
function getToken(){return localStorage.getItem(TOKEN_KEY);}
function clearToken(){localStorage.removeItem(TOKEN_KEY);}
function openSidebar(){sidebar.classList.add('is-open');sidebarOverlay.classList.add('is-open');}
function closeSidebar(){sidebar.classList.remove('is-open');sidebarOverlay.classList.remove('is-open');}
function updateScrollHint(){const canScroll=sidebarScroll.scrollHeight>sidebarScroll.clientHeight;const nearBottom=sidebarScroll.scrollTop+sidebarScroll.clientHeight>=sidebarScroll.scrollHeight-4;scrollHint.classList.toggle('is-visible',canScroll&&!nearBottom);}
function escapeHtml(value){return String(value??'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#39;');}
function toMoney(value){const amount=Number(value||0);return '$'+amount.toLocaleString('en-US',{minimumFractionDigits:2,maximumFractionDigits:2});}
function toMoneyFromCents(value){const amount=Number(value||0)/100;return '$'+amount.toLocaleString('en-US',{minimumFractionDigits:2,maximumFractionDigits:2});}
function formatDateTime(value){if(!value){return '-';}const date=new Date(value);if(Number.isNaN(date.getTime())){return String(value);}return date.toLocaleString('es-MX');}
function getLoanStatusLabel(status){const labels={offered:'Prestamo ofrecido',pending:'Prestamo pendiente',approved:'Prestamo aprobado',rejected:'Prestamo rechazado',paid:'Prestamo pagado'};return labels[String(status||'').toLowerCase()]||'Estado no definido';}
function getLoanFrequencyLabel(frequency){const labels={weekly:'Semanal',biweekly:'Quincenal',monthly:'Mensual'};return labels[String(frequency||'').toLowerCase()]||'Frecuencia no definida';}
function setFeedback(message,type){loanFeedback.textContent=message;loanFeedback.className='feedback-box '+type;}
function clearFeedback(){loanFeedback.textContent='';loanFeedback.className='feedback-box is-hidden';}
function updateBalanceNote(){parentBalanceNote.textContent='* Cuentas con '+toMoneyFromCents(currentParentBalance)+' para dar prestamos';}
async function apiRequest(path,method,payload){const token=getToken();const headers={Accept:'application/json','Content-Type':'application/json'};if(token){headers.Authorization='Bearer '+token;}const response=await fetch(API_BASE+path,{method,headers,body:payload?JSON.stringify(payload):undefined});const data=await response.json().catch(()=>({}));if(!response.ok){throw {status:response.status,data};}return data;}
function showList(){closeLoanDetail();loansListPanel.classList.remove('is-hidden');membersPanel.classList.add('is-hidden');formPanel.classList.add('is-hidden');loansTabList.classList.remove('gold-btn');loansTabList.classList.add('blue-btn');loansTabCreate.classList.remove('blue-btn');loansTabCreate.classList.add('gold-btn');}
function showCreate(){closeLoanDetail();loansListPanel.classList.add('is-hidden');membersPanel.classList.remove('is-hidden');formPanel.classList.add('is-hidden');loansTabCreate.classList.remove('gold-btn');loansTabCreate.classList.add('blue-btn');loansTabList.classList.remove('blue-btn');loansTabList.classList.add('gold-btn');renderMembersForLoan();}
function openLoanForm(target){selectedMemberId=target.user_id;selectedMemberLabel.textContent=target.name+' (@'+(target.username||'sin_username')+')';membersPanel.classList.add('is-hidden');formPanel.classList.remove('is-hidden');clearFeedback();updateLoanAmountState();}
function renderMembersForLoan(){if(loanTargets.length===0){emptyMessage.classList.remove('is-hidden');membersList.innerHTML='';return;}emptyMessage.classList.add('is-hidden');membersList.innerHTML=loanTargets.map(target=>'<button class="btn blue-btn" type="button" data-member-id="'+Number(target.user_id)+'">'+escapeHtml(target.name)+' (@'+escapeHtml(target.username||'sin_username')+')</button>').join('');membersList.querySelectorAll('button[data-member-id]').forEach(button=>button.addEventListener('click',()=>{const target=loanTargets.find(item=>String(item.user_id)===button.dataset.memberId);if(target){openLoanForm(target);}}));}
function updateLoanAmountState(){const amount=Number(loanAmount.value);const invalid=Number.isFinite(amount)&&amount*100>currentParentBalance;loanAmount.classList.toggle('input-invalid',invalid);loanSubmit.disabled=invalid;if(invalid){setFeedback('El monto supera tu balance disponible.','feedback-error');return;}if(loanFeedback.textContent==='El monto supera tu balance disponible.'){clearFeedback();}}
function closeLoanDetail(){if(currentLoanDetailPanel){currentLoanDetailPanel.remove();currentLoanDetailPanel=null;}loansListPanel.classList.remove('is-hidden');}
function renderLoanDetail(loan){closeLoanDetail();const template=document.getElementById('template-loan-details');currentLoanDetailPanel=template.content.firstElementChild.cloneNode(true);currentLoanDetailPanel.classList.remove('is-hidden');currentLoanDetailPanel.querySelector('#loan-detail-back').addEventListener('click',closeLoanDetail);currentLoanDetailPanel.querySelector('[data-field="amount"]').textContent=toMoney(loan.amount);currentLoanDetailPanel.querySelector('[data-field="total_amount"]').textContent=toMoney(loan.total_amount);currentLoanDetailPanel.querySelector('[data-field="child"]').textContent=loan.child?.name?loan.child.name+' (@'+(loan.child.username||'sin_username')+')':'-';currentLoanDetailPanel.querySelector('[data-field="reason"]').textContent=loan.reason||'-';currentLoanDetailPanel.querySelector('[data-field="due_date"]').textContent=loan.due_date||'-';currentLoanDetailPanel.querySelector('[data-field="status"]').textContent=getLoanStatusLabel(loan.status);currentLoanDetailPanel.querySelector('[data-field="installments"]').textContent=String(loan.installments_count||1)+' x '+toMoney(loan.installment_amount);currentLoanDetailPanel.querySelector('[data-field="frequency"]').textContent=getLoanFrequencyLabel(loan.installment_frequency);currentLoanDetailPanel.querySelector('[data-field="interest"]').textContent=(loan.has_interest?'Si ':'No ')+(loan.interest_mode==='fixed'?'('+toMoney(loan.fixed_interest_amount)+')':'('+(Number(loan.annual_interest_rate)||0).toFixed(2)+'%)');currentLoanDetailPanel.querySelector('[data-field="created_at"]').textContent=formatDateTime(loan.created_at);loansListPanel.after(currentLoanDetailPanel);loansListPanel.classList.add('is-hidden');currentLoanDetailPanel.scrollIntoView({behavior:'smooth',block:'start'});}
function renderLoanCard(loan,allowApprove){const childName=loan.child?.name||'Miembro';let action='';if(allowApprove){const canApprove=currentParentBalance>=Number(loan.amount||0)*100;if(canApprove){action='<button class="btn gold-btn btn-inline parent-approve-btn mt-1" type="button" data-loan-id="'+Number(loan.id)+'">Aceptar</button>';}else{action='<div class="feedback-box feedback-error mt-1">No tienes fondos suficientes.</div>';}}return '<section class="quick-card mt-1 loan-summary-card" data-loan-id="'+Number(loan.id)+'"><div class="top-row loan-summary-top"><div><p class="quick-card-title">'+escapeHtml(childName)+'</p><p class="quick-card-subtitle">'+escapeHtml(loan.reason||'Prestamo')+' | '+toMoney(loan.total_amount||loan.amount)+' | '+escapeHtml(getLoanStatusLabel(loan.status))+'</p></div><button class="loan-expand-btn" type="button" aria-label="Ver detalle">v</button></div>'+action+'</section>';}
function bindLoanCards(){loansList.querySelectorAll('.loan-summary-card').forEach(card=>card.addEventListener('click',()=>{const loan=loansById.get(Number(card.dataset.loanId));if(loan){renderLoanDetail(loan);}}));loansList.querySelectorAll('.parent-approve-btn').forEach(button=>button.addEventListener('click',event=>{event.stopPropagation();button.disabled=true;approvePendingLoan(Number(button.dataset.loanId)).finally(()=>{button.disabled=false;});}));}
async function fetchFamilyMembers(){const response=await apiRequest('/family-members','GET');loanTargets=response.loan_targets||[];}
async function loadLoansList(){try{const response=await apiRequest('/loans','GET');const loans=response.loans||[];loansById=new Map(loans.map(loan=>[Number(loan.id),loan]));const pending=loans.filter(loan=>loan.status==='pending');const active=loans.filter(loan=>loan.status==='offered'||loan.status==='approved');const rejected=loans.filter(loan=>loan.status==='rejected');const sections=[];if(pending.length){sections.push('<section class="quick-card"><p class="quick-card-title">Solicitudes pendientes</p>'+pending.map(loan=>renderLoanCard(loan,true)).join('')+'</section>');}if(active.length){sections.push('<section class="quick-card mt-1"><p class="quick-card-title">Prestamos activos y ofrecidos</p>'+active.map(loan=>renderLoanCard(loan,false)).join('')+'</section>');}if(rejected.length){sections.push('<section class="quick-card mt-1"><p class="quick-card-title">Prestamos rechazados</p>'+rejected.map(loan=>renderLoanCard(loan,false)).join('')+'</section>');}if(sections.length===0){loansList.innerHTML='<section class="quick-card"><p class="quick-card-title">Sin prestamos</p><p class="quick-card-subtitle">Todavia no hay prestamos registrados.</p><button id="empty-create-loan-btn" class="btn gold-btn btn-inline mt-1" type="button">Dar un prestamo</button></section>';document.getElementById('empty-create-loan-btn').addEventListener('click',showCreate);return;}loansList.innerHTML=sections.join('');bindLoanCards();}catch(error){loansList.innerHTML='<section class="quick-card"><p class="quick-card-title">Error</p><p class="quick-card-subtitle">'+escapeHtml(error?.data?.message||'No se pudo cargar prestamos.')+'</p></section>';}}
async function approvePendingLoan(loanId){try{const response=await apiRequest('/loans/'+loanId+'/approve','POST');if(Number.isFinite(Number(response.remaining_balance))){currentParentBalance=Number(response.remaining_balance);updateBalanceNote();}setFeedback('Prestamo aprobado correctamente.','feedback-success');await loadLoansList();}catch(error){setFeedback(error?.data?.message||'No se pudo aprobar el prestamo.','feedback-error');}}
function getInstallmentsCount(){if(loanModeSingle.checked){return 1;}const custom=Number(customInstallments.value);if(Number.isInteger(custom)&&custom>0){return custom;}return selectedInstallments;}
function getInterestPayload(){const payload={has_interest:withInterest.checked,interest_mode:'percent',annual_interest_rate:0,fixed_interest_amount:0};if(!withInterest.checked){return payload;}const selectedValue=Number(customInterest.value)||selectedInterest;if(interestModeFixed.checked){payload.interest_mode='fixed';payload.fixed_interest_amount=Math.max(0,Math.round(selectedValue));return payload;}payload.annual_interest_rate=Math.max(0,selectedValue);return payload;}
loanForm.addEventListener('submit',async event=>{event.preventDefault();clearFeedback();if(!selectedMemberId){setFeedback('Selecciona un miembro antes de crear el prestamo.','feedback-error');return;}updateLoanAmountState();if(loanSubmit.disabled){return;}const interestPayload=getInterestPayload();const payload={child_user_id:selectedMemberId,amount:Number(loanAmount.value),reason:loanReason.value.trim()||null,due_date:loanDueDate.value,installments_count:getInstallmentsCount(),installment_frequency:frequency.value,has_interest:interestPayload.has_interest,interest_mode:interestPayload.interest_mode,annual_interest_rate:interestPayload.annual_interest_rate,fixed_interest_amount:interestPayload.fixed_interest_amount};try{const response=await apiRequest('/loans','POST',payload);if(Number.isFinite(Number(response.remaining_balance))){currentParentBalance=Number(response.remaining_balance);updateBalanceNote();}loanForm.reset();selectedInstallments=3;selectedInterest=0;loanAmount.classList.remove('input-invalid');deferredPaymentFields.classList.add('is-hidden');interestModeRow.classList.add('is-hidden');interestOptions.classList.add('is-hidden');customInterest.classList.add('is-hidden');setFeedback('Prestamo ofrecido correctamente.','feedback-success');await loadLoansList();}catch(error){setFeedback(error?.data?.message||'No se pudo crear el prestamo.','feedback-error');}});
loanAmount.addEventListener('input',updateLoanAmountState);
loanBack.addEventListener('click',showCreate);
loansTabList.addEventListener('click',showList);
loansTabCreate.addEventListener('click',showCreate);
loanModeSingle.addEventListener('change',()=>{deferredPaymentFields.classList.add('is-hidden');});
loanModeDeferred.addEventListener('change',()=>{deferredPaymentFields.classList.remove('is-hidden');});
withInterest.addEventListener('change',()=>{interestModeRow.classList.toggle('is-hidden',!withInterest.checked);interestOptions.classList.toggle('is-hidden',!withInterest.checked);customInterest.classList.toggle('is-hidden',!withInterest.checked);});
Array.from(document.querySelectorAll('.interest-btn')).forEach(button=>button.addEventListener('click',()=>{selectedInterest=Number(button.dataset.rate)||0;customInterest.value='';}));
Array.from(document.querySelectorAll('.installment-btn')).forEach(button=>button.addEventListener('click',()=>{selectedInstallments=Number(button.dataset.count)||1;customInstallments.value='';}));
logoutButton.addEventListener('click',async()=>{try{await apiRequest('/logout','POST');}catch(error){}finally{clearToken();window.location.href='/login';}});
menuButton.addEventListener('click',()=>{if(sidebar.classList.contains('is-open')){closeSidebar();return;}openSidebar();updateScrollHint();});
sidebarClose.addEventListener('click',closeSidebar);
sidebarOverlay.addEventListener('click',closeSidebar);
sidebarScroll.addEventListener('scroll',updateScrollHint);
window.addEventListener('resize',()=>{if(window.innerWidth>=768){closeSidebar();}updateScrollHint();});
(async function bootstrap(){updateScrollHint();if(!getToken()){window.location.href='/login';return;}try{const me=await apiRequest('/me','GET');if(me.user?.role!=='parent'){window.location.href='/member/loans';return;}sidebarUserName.textContent=me.user?.name||'Usuario';currentParentBalance=Number(me.user?.balance_cents||0);updateBalanceNote();await fetchFamilyMembers();await loadLoansList();showList();}catch(error){if(error.status===401){clearToken();window.location.href='/login';return;}loansList.innerHTML='<section class="quick-card"><p class="quick-card-title">Error de sesion</p><p class="quick-card-subtitle">No se pudo validar la sesion.</p></section>';}})();
</script>
</body>
</html>
