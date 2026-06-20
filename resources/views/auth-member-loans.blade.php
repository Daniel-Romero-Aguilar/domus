<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Prestamos (Miembro)</title>
    <link rel="stylesheet" href="/css/styles.css">
    <script src="/js/domus-achievements.js"></script>
    <style>
        .loan-summary-card{cursor:pointer;}
        .loan-summary-top{align-items:center;gap:.75rem;}
        .loan-expand-btn{width:2.2rem;height:2.2rem;border:0;border-radius:999px;background:rgba(255,255,255,.16);color:#fff;font-size:1.1rem;line-height:1;transition:transform .18s ease,background .18s ease;}
        .loan-summary-card:hover .loan-expand-btn{background:rgba(255,255,255,.24);transform:rotate(180deg);}
        .loan-detail-card{background:#f8fafc;color:#10234a;}
        .loan-detail-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:.85rem 1rem;}
        .loan-detail-stack{display:grid;gap:.85rem;}
        .loan-detail-item{min-width:0;}
        .loan-detail-label{margin:0 0 .15rem;color:#b0891f;font-size:.8rem;font-weight:700;text-transform:uppercase;letter-spacing:.04em;}
        .loan-detail-value{margin:0;color:#10234a;font-weight:700;font-size:.94rem;line-height:1.35;overflow-wrap:anywhere;word-break:break-word;}
        .loan-meta-note{margin:.45rem 0 0;color:#eef4ff;font-size:.95rem;line-height:1.45;}
        .loan-card-facts{display:flex;flex-wrap:wrap;gap:.4rem .75rem;margin:.4rem 0 0;}
        .loan-card-fact{margin:0;color:#dbeafe;font-size:.82rem;line-height:1.35;}
        .loan-payment-actions{display:flex;flex-wrap:wrap;gap:.65rem;margin-top:.85rem;}
        .loan-payment-stats{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:.75rem;}
        .loan-stat-box{padding:.85rem 1rem;border:1px solid #d8e0f2;border-radius:.85rem;background:#fff;}
        .loan-stat-box p{margin:0;}
        .loan-stat-label{color:#6b7a99;font-size:.82rem;font-weight:600;}
        .loan-stat-value{margin-top:.2rem;color:#10234a;font-size:1.05rem;font-weight:800;}
        .loan-payments-list{display:grid;gap:.75rem;}
        .loan-payment-card{padding:.8rem;border:1px solid #d8e0f2;border-radius:.85rem;background:#fff;}
        .loan-payment-card-top{display:flex;align-items:center;justify-content:space-between;gap:.6rem;flex-wrap:wrap;margin-bottom:.65rem;}
        .loan-payment-card-number{margin:0;color:#10234a;font-size:.95rem;font-weight:800;}
        .loan-payment-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:.65rem .8rem;}
        .loan-payment-item{min-width:0;}
        .loan-payment-label{margin:0 0 .12rem;color:#6b7a99;font-size:.68rem;font-weight:800;text-transform:uppercase;letter-spacing:.03em;}
        .loan-payment-value{margin:0;color:#10234a;font-size:.82rem;font-weight:700;line-height:1.3;overflow-wrap:anywhere;word-break:break-word;}
        .loan-payment-empty{padding:.9rem;border:1px dashed #d8e0f2;border-radius:.85rem;background:#fff;color:#6b7a99;font-size:.88rem;}
        .loan-payment-value .status-chip{padding:.14rem .45rem;font-size:.72rem;}
        @media (max-width: 820px){.loan-payment-grid{grid-template-columns:repeat(2,minmax(0,1fr));}}
        @media (max-width: 480px){.loan-detail-grid,.loan-payment-grid{grid-template-columns:1fr;}}
        .status-chip{display:inline-flex;align-items:center;padding:.18rem .55rem;border-radius:999px;font-size:.78rem;font-weight:700;}
        .status-chip.pending{background:#eef4ff;color:#19439a;}
        .status-chip.overdue{background:#fff2f0;color:#b9382f;}
        .status-chip.paid{background:#edf8ef;color:#267447;}
    </style>
</head>
<body class="app-bg">
<main class="layout">
    <div id="sidebar-overlay" class="sidebar-overlay"></div>
    <aside id="sidebar" class="sidebar">
        <button id="sidebar-close" class="sidebar-close" type="button">x</button>
        <div class="sidebar-logo-wrap"><img class="sidebar-logo" src="/img/domus_logo.png" alt="Domus logo"></div>
        <section class="profile-card"><p id="sidebar-user-name" class="profile-name">Usuario</p><a id="sidebar-user-level" class="profile-level" href="/levels" style="display:block;color:inherit;text-decoration:none;">Cargando nivel...</a></section>
        <div id="sidebar-scroll" class="sidebar-scroll">
            <nav class="sidebar-nav">
                <a class="sidebar-link" href="/account"><span class="nav-icon">&bull;</span><span>Inicio</span></a>
                <a class="sidebar-link" href="/account/users"><span class="nav-icon">&bull;</span><span>Usuarios</span></a>
                <a class="sidebar-link is-active" href="/member/loans"><span class="nav-icon">&bull;</span><span>Prestamos</span></a>
                <a class="sidebar-link" href="/child/savings-boxes"><span class="nav-icon">&bull;</span><span>Cajas de ahorro</span></a>
                <a class="sidebar-link" href="/child/goals"><span class="nav-icon">&bull;</span><span>Metas</span></a>
                <a class="sidebar-link" href="/child/tasks"><span class="nav-icon">&bull;</span><span>Tareas</span></a>
                <a class="sidebar-link" href="/child/domus-points"><span class="nav-icon">&bull;</span><span>Puntos Domus</span></a>
                <a class="sidebar-link" href="/account/education"><span class="nav-icon">&bull;</span><span>Educacion</span></a>
            </nav>
            <div id="scroll-hint" class="scroll-hint">Desliza para ver mas</div>
        </div>
    </aside>

    <section class="page content card">
        <div class="top-row"><button id="menu-btn" class="btn blue-btn menu-toggle" type="button">Menu</button><button id="logout-btn" class="btn btn-inline gold-btn right" type="button">Cerrar sesion</button></div>
        <p class="brand mt-2">DOMUS</p>
        <h1>Prestamos</h1>
        <p class="subtitle">Pide prestamos y responde ofertas de tu administrador.</p>
        <p id="member-request-note" class="legal-note mt-1"></p>

        <div class="top-row mt-1">
            <button id="loans-tab-list" class="btn blue-btn btn-inline" type="button">Mis prestamos</button>
            <button id="loans-tab-create" class="btn gold-btn btn-inline" type="button">Pedir prestamo</button>
        </div>

        <section id="loans-list-panel" class="mt-2"><div id="loans-list"></div></section>

        <section id="loan-form-panel" class="mt-2 is-hidden">
            <div class="quick-card"><p class="quick-card-title">Prestamo solicitado por ti</p></div>
            <form id="loan-form" class="form">
                <div class="field"><label for="loan-amount">Cantidad</label><input id="loan-amount" class="input" type="number" min="1" step="1" required></div>
                <div class="field"><label for="loan-reason">Motivo</label><input id="loan-reason" class="input" type="text" maxlength="120" placeholder="Ejemplo: Laptop para escuela"></div>
                <div class="field"><label id="loan-date-label" for="loan-due-date">Fecha limite de pago</label><input id="loan-due-date" class="input" type="date" required></div>
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
                <div class="top-row mt-2"><button id="loan-back" class="btn btn-inline blue-btn" type="button">Volver</button><button id="loan-submit" class="btn btn-inline gold-btn" type="submit">Pedir prestamo</button></div>
            </form>
        </section>
    </section>
</main>

<template id="template-loan-details">
    <section id="loan-detail-panel" class="quick-card loan-detail-card mt-1 is-hidden">
        <div class="top-row"><p class="quick-card-title">Detalle del prestamo</p><button id="loan-detail-back" class="btn btn-inline blue-btn" type="button">Volver</button></div>
        <div class="loan-detail-grid mt-1">
            <div class="loan-detail-item"><p class="loan-detail-label">Primer pago</p><p data-field="due_date" class="loan-detail-value"></p></div>
            <div class="loan-detail-item"><p class="loan-detail-label">Prestado</p><p data-field="amount" class="loan-detail-value"></p></div>
            <div class="loan-detail-item"><p class="loan-detail-label">Total a pagar</p><p data-field="total_amount" class="loan-detail-value"></p></div>
            <div class="loan-detail-item"><p class="loan-detail-label">Frecuencia</p><p data-field="frequency" class="loan-detail-value"></p></div>
            <div class="loan-detail-item"><p class="loan-detail-label">Intereses</p><p data-field="interest" class="loan-detail-value"></p></div>
            <div class="loan-detail-item"><p class="loan-detail-label">Creado</p><p data-field="created_at" class="loan-detail-value"></p></div>
        </div>
        <div class="loan-detail-stack mt-1">
            <div class="loan-detail-item"><p class="loan-detail-label">Administrador</p><p data-field="parent" class="loan-detail-value"></p></div>
            <div class="loan-detail-item"><p class="loan-detail-label">Motivo</p><p data-field="reason" class="loan-detail-value"></p></div>
            <div class="loan-detail-item"><p class="loan-detail-label">Estado</p><p data-field="status" class="loan-detail-value"></p></div>
            <div class="loan-detail-item"><p class="loan-detail-label">Pagos</p><p data-field="installments" class="loan-detail-value"></p></div>
        </div>
        <section class="mt-2">
            <p class="quick-card-title">Resumen de pagos</p>
            <div data-field="payment-summary" class="loan-payment-stats mt-1"></div>
        </section>
        <section class="mt-2">
            <p class="quick-card-title">Historial de pagos</p>
            <div data-field="payments-rows" class="loan-payments-list mt-1"></div>
        </section>
    </section>
</template>

<script>
const API_BASE='/api',TOKEN_KEY='parent_auth_token';
let currentUserId=null,currentMemberBalance=0,selectedInstallments=3,selectedInterest=0,currentLoanDetailPanel=null,loansById=new Map();
const logoutButton=document.getElementById('logout-btn'),menuButton=document.getElementById('menu-btn'),sidebar=document.getElementById('sidebar'),sidebarUserName=document.getElementById('sidebar-user-name'),sidebarUserLevel=document.getElementById('sidebar-user-level'),sidebarClose=document.getElementById('sidebar-close'),sidebarOverlay=document.getElementById('sidebar-overlay'),sidebarScroll=document.getElementById('sidebar-scroll'),scrollHint=document.getElementById('scroll-hint');
const loansTabList=document.getElementById('loans-tab-list'),loansTabCreate=document.getElementById('loans-tab-create'),loansListPanel=document.getElementById('loans-list-panel'),loansList=document.getElementById('loans-list'),formPanel=document.getElementById('loan-form-panel'),loanForm=document.getElementById('loan-form'),loanBack=document.getElementById('loan-back'),loanFeedback=document.getElementById('loan-feedback'),memberRequestNote=document.getElementById('member-request-note');
const loanAmount=document.getElementById('loan-amount'),loanReason=document.getElementById('loan-reason'),loanDueDate=document.getElementById('loan-due-date'),loanDateLabel=document.getElementById('loan-date-label'),customInstallments=document.getElementById('custom-installments'),frequency=document.getElementById('installment-frequency'),withInterest=document.getElementById('with-interest'),interestOptions=document.getElementById('interest-options'),customInterest=document.getElementById('custom-interest'),loanModeSingle=document.getElementById('loan-mode-single'),loanModeDeferred=document.getElementById('loan-mode-deferred'),deferredPaymentFields=document.getElementById('deferred-payment-fields'),interestModeRow=document.getElementById('interest-mode-row'),interestModeFixed=document.getElementById('interest-mode-fixed');
const interestModePercent=document.getElementById('interest-mode-percent');
function getToken(){return localStorage.getItem(TOKEN_KEY);}
function clearToken(){localStorage.removeItem(TOKEN_KEY);}
function openSidebar(){sidebar.classList.add('is-open');sidebarOverlay.classList.add('is-open');}
function closeSidebar(){sidebar.classList.remove('is-open');sidebarOverlay.classList.remove('is-open');}
function updateScrollHint(){const canScroll=sidebarScroll.scrollHeight>sidebarScroll.clientHeight;const nearBottom=sidebarScroll.scrollTop+sidebarScroll.clientHeight>=sidebarScroll.scrollHeight-4;scrollHint.classList.toggle('is-visible',canScroll&&!nearBottom);}
function escapeHtml(value){return String(value??'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#39;');}
function toMoney(value){const amount=Number(value||0);return '$'+amount.toLocaleString('en-US',{minimumFractionDigits:2,maximumFractionDigits:2});}
function toMoneyFromCents(value){const amount=Number(value||0)/100;return '$'+amount.toLocaleString('en-US',{minimumFractionDigits:2,maximumFractionDigits:2});}
function formatDateTime(value){if(!value){return '-';}const date=new Date(value);if(Number.isNaN(date.getTime())){return String(value);}return date.toLocaleString('es-MX');}
function formatDate(value){if(!value){return '-';}const normalized=String(value).slice(0,10);return /^\d{4}-\d{2}-\d{2}$/.test(normalized)?normalized:String(value);}
function getLoanStatusLabel(status){const labels={offered:'Prestamo ofrecido',pending:'Prestamo pendiente',approved:'Prestamo aprobado',rejected:'Prestamo rechazado',paid:'Prestamo pagado'};return labels[String(status||'').toLowerCase()]||'Estado no definido';}
function getLoanFrequencyLabel(frequency){const labels={weekly:'Semanal',biweekly:'Quincenal',monthly:'Mensual'};return labels[String(frequency||'').toLowerCase()]||'Frecuencia no definida';}
function getInstallmentSummary(loan){return loan.installment_plan?.summary||String(loan.installments_count||1)+' x '+toMoney(loan.installment_amount);}
function getLoanInterestSummary(loan){if(!loan?.has_interest){return 'Sin interes';}if(String(loan.interest_mode||'percent')==='fixed'){return toMoney(loan.fixed_interest_amount||0);}return Number(loan.annual_interest_rate||0).toFixed(2)+'%';}
function getLoanPaymentPlanSummary(loan){const count=Number(loan.installments_count||1);if(count<=1){return 'Pago unico el dia '+formatDate(loan.due_date);}const frequency=getLoanFrequencyLabel(loan.installment_frequency).toLowerCase();return count+' pagos '+frequency+'es';}
function getPaymentSummary(loan){return loan.payment_summary||{total_installments:0,paid_installments:0,pending_installments:0,overdue_installments:0,payable_total_cents:0,payable_payments:[],next_payment:null,next_upcoming_payment:null};}
function getStatusChip(status,label){return '<span class="status-chip '+escapeHtml(status||'pending')+'">'+escapeHtml(label||'Pendiente')+'</span>';}
function getLoanDateLabel(loan){return Number(loan.installments_count||1)>1?'Primer pago':'Fecha limite';}
function updateMemberNote(){memberRequestNote.textContent='Tu saldo disponible es '+toMoneyFromCents(currentMemberBalance)+'.';}
async function loadSidebarLevel(){try{const data=await apiRequest('/child/domus-points','GET');const level=data.level;sidebarUserLevel.textContent=level?('Nivel '+Number(level.level_number||1)+' - '+level.name):'Sin nivel';}catch(_error){sidebarUserLevel.textContent='Sin nivel';}}
function renderMemberCardNote(loan){const summary=getPaymentSummary(loan);if(loan.status==='offered'){return 'Tienes una oferta lista para responder.';}if(loan.status==='pending'){return 'Tu solicitud sigue en revision.';}if(loan.status==='rejected'){return 'Esta solicitud fue rechazada.';}if(loan.status==='paid'){return 'Ya terminaste de pagar este prestamo.';}if(summary.payable_payments.length===0){const next=summary.next_payment;if(!next){return 'No tienes pagos pendientes.';}if(next.due_date===new Date().toISOString().slice(0,10)){return 'Siguiente pago: hoy por '+next.total_amount_display+'.';}return 'Siguiente pago: '+formatDate(next.due_date)+' por '+next.total_amount_display+'.';}if(summary.payable_payments.length===1){const payment=summary.payable_payments[0];if(payment.status==='overdue'){return 'Tienes 1 pago pendiente por '+payment.total_amount_display+'.';}return 'Hoy ya puedes pagar '+payment.total_amount_display+'.';}const upcoming=summary.next_upcoming_payment;return 'Debes '+summary.payable_payments.length+' pagos por '+toMoneyFromCents(summary.payable_total_cents)+'. '+(upcoming?'Ademas, viene otro el '+formatDate(upcoming.due_date)+'.':'');}
function renderPayButtons(loan,payments){if(loan.status!=='approved'||!Array.isArray(payments)||payments.length===0){return '';}return '<div class="loan-payment-actions">'+payments.map(payment=>'<button class="btn gold-btn btn-inline pay-loan-btn" type="button" data-loan-payment-id="'+Number(payment.id)+'">Pagar cuota #'+Number(payment.installment_number||0)+' ('+escapeHtml(payment.total_amount_display||toMoney(payment.total_amount))+')</button>').join('')+'</div>';}
function renderPaymentSummaryBoxes(summary){return '<div class="loan-stat-box"><p class="loan-stat-label">Pagos</p><p class="loan-stat-value">'+Number(summary.paid_installments||0)+' pagados / '+Number(summary.total_installments||0)+'</p></div><div class="loan-stat-box"><p class="loan-stat-label">Pendientes</p><p class="loan-stat-value">'+Number(summary.pending_installments||0)+'</p></div><div class="loan-stat-box"><p class="loan-stat-label">Vencidos</p><p class="loan-stat-value">'+Number(summary.overdue_installments||0)+'</p></div><div class="loan-stat-box"><p class="loan-stat-label">Pagado</p><p class="loan-stat-value">'+toMoneyFromCents(summary.paid_total_cents||0)+'</p></div><div class="loan-stat-box"><p class="loan-stat-label">Capital pagado</p><p class="loan-stat-value">'+toMoneyFromCents(summary.paid_principal_cents||0)+'</p></div><div class="loan-stat-box"><p class="loan-stat-label">Intereses pagados</p><p class="loan-stat-value">'+toMoneyFromCents(summary.paid_interest_cents||0)+'</p></div>';}
function renderPaymentRows(payments,loanStatus){if(!Array.isArray(payments)||payments.length===0){return '<div class="loan-payment-empty">Este prestamo aun no tiene pagos programados.</div>';}return payments.map(payment=>{const actionMarkup=(loanStatus==='approved'&&payment.is_payable_today&&payment.status!=='paid')?'<div class="loan-payment-item"><p class="loan-payment-label">Accion</p><div class="loan-payment-value"><button class="btn gold-btn btn-inline pay-loan-btn" type="button" data-loan-payment-id="'+Number(payment.id)+'">Pagar</button></div></div>':'';return '<article class="loan-payment-card"><div class="loan-payment-card-top"><p class="loan-payment-card-number">Pago '+Number(payment.installment_number||0)+'</p><span class="loan-payment-value">'+getStatusChip(payment.status,payment.status_label)+'</span></div><div class="loan-payment-grid"><div class="loan-payment-item"><p class="loan-payment-label">Fecha</p><p class="loan-payment-value">'+escapeHtml(formatDate(payment.due_date))+'</p></div><div class="loan-payment-item"><p class="loan-payment-label">Total</p><p class="loan-payment-value">'+escapeHtml(payment.total_amount_display||toMoney(payment.total_amount))+'</p></div><div class="loan-payment-item"><p class="loan-payment-label">Capital</p><p class="loan-payment-value">'+escapeHtml(payment.principal_amount_display||toMoney(payment.principal_amount))+'</p></div><div class="loan-payment-item"><p class="loan-payment-label">Interes</p><p class="loan-payment-value">'+escapeHtml(payment.interest_amount_display||toMoney(payment.interest_amount))+'</p></div><div class="loan-payment-item"><p class="loan-payment-label">Pagado</p><p class="loan-payment-value">'+escapeHtml(payment.paid_at?formatDateTime(payment.paid_at):'-')+'</p></div>'+actionMarkup+'</div></article>';}).join('');}
function setFeedback(message,type){loanFeedback.textContent=message;loanFeedback.className='feedback-box '+type;}
function clearFeedback(){loanFeedback.textContent='';loanFeedback.className='feedback-box is-hidden';}
function syncChoicePillState(){document.querySelectorAll('.choice-pill').forEach(pill=>{const input=pill.querySelector('input');pill.classList.toggle('is-selected',Boolean(input&&input.checked));});}
function setSelectedButton(selector,selectedButton){document.querySelectorAll(selector).forEach(button=>button.classList.toggle('is-selected',button===selectedButton));}
function resetLoanSelections(){selectedInstallments=3;selectedInterest=0;setSelectedButton('.installment-btn',document.querySelector('.installment-btn[data-count="3"]'));setSelectedButton('.interest-btn',null);customInstallments.classList.remove('input-selected');customInterest.classList.remove('input-selected');syncChoicePillState();}
function updateLoanDateLabel(){loanDateLabel.textContent=loanModeSingle.checked?'Fecha limite de pago':'Fecha del primer pago';}
function getFixedInterestSuggestions(amount){if(!Number.isFinite(amount)||amount<=0){return [1,5,10,15];}if(amount<=100){return [1,5,10,15];}if(amount<=1000){return [10,50,100,150];}if(amount<=10000){return [100,500,1000,1500];}if(amount<=100000){return [1000,5000,10000,15000];}const scale=Math.pow(10,Math.max(0,String(Math.floor(amount)).length-3));return [1,5,10,15].map(value=>value*scale);}
function updateInterestOptionsUI(){const isFixed=interestModeFixed.checked;const amount=Number(loanAmount.value);const suggestions=isFixed?getFixedInterestSuggestions(amount):[1,5,10,12];const buttons=Array.from(document.querySelectorAll('.interest-btn'));buttons.forEach((button,index)=>{const value=Number(suggestions[index]||0);button.dataset.rate=String(value);button.textContent=isFixed?('$'+value.toLocaleString('en-US')):(value+'%');});customInterest.placeholder=isFixed?'Interes fijo personalizado':'Interes personalizado';if(!customInterest.value.trim()){selectedInterest=0;setSelectedButton('.interest-btn',null);}}
async function apiRequest(path,method,payload){const token=getToken();const headers={Accept:'application/json','Content-Type':'application/json'};if(token){headers.Authorization='Bearer '+token;}const response=await fetch(API_BASE+path,{method,headers,body:payload?JSON.stringify(payload):undefined});const data=await response.json().catch(()=>({}));if(!response.ok){throw {status:response.status,data};}return data;}
function showList(){closeLoanDetail();loansListPanel.classList.remove('is-hidden');formPanel.classList.add('is-hidden');loansTabList.classList.remove('gold-btn');loansTabList.classList.add('blue-btn');loansTabCreate.classList.remove('blue-btn');loansTabCreate.classList.add('gold-btn');}
function showCreate(){closeLoanDetail();loansListPanel.classList.add('is-hidden');formPanel.classList.remove('is-hidden');loansTabCreate.classList.remove('gold-btn');loansTabCreate.classList.add('blue-btn');loansTabList.classList.remove('blue-btn');loansTabList.classList.add('gold-btn');clearFeedback();}
function closeLoanDetail(){if(currentLoanDetailPanel){currentLoanDetailPanel.remove();currentLoanDetailPanel=null;}loansListPanel.classList.remove('is-hidden');}
function renderLoanDetail(loan){const summary=getPaymentSummary(loan);closeLoanDetail();const template=document.getElementById('template-loan-details');currentLoanDetailPanel=template.content.firstElementChild.cloneNode(true);currentLoanDetailPanel.classList.remove('is-hidden');currentLoanDetailPanel.querySelector('#loan-detail-back').addEventListener('click',closeLoanDetail);currentLoanDetailPanel.querySelector('[data-field="amount"]').textContent=toMoney(loan.amount);currentLoanDetailPanel.querySelector('[data-field="total_amount"]').textContent=toMoney(loan.total_amount);currentLoanDetailPanel.querySelector('[data-field="parent"]').textContent=loan.parent?.name||loan.parent?.email||'-';currentLoanDetailPanel.querySelector('[data-field="reason"]').textContent=loan.reason||'-';currentLoanDetailPanel.querySelector('[data-field="due_date"]').textContent=formatDate(loan.due_date);currentLoanDetailPanel.querySelector('[data-field="status"]').textContent=getLoanStatusLabel(loan.status);currentLoanDetailPanel.querySelector('[data-field="installments"]').textContent=getInstallmentSummary(loan);currentLoanDetailPanel.querySelector('[data-field="frequency"]').textContent=getLoanFrequencyLabel(loan.installment_frequency);currentLoanDetailPanel.querySelector('[data-field="interest"]').textContent=(loan.has_interest?'Si ':'No ')+(loan.interest_mode==='fixed'?'('+toMoney(loan.fixed_interest_amount)+')':'('+(Number(loan.annual_interest_rate)||0).toFixed(2)+'%)');currentLoanDetailPanel.querySelector('[data-field="created_at"]').textContent=formatDate(loan.created_at);currentLoanDetailPanel.querySelector('[data-field="payment-summary"]').innerHTML=renderPaymentSummaryBoxes(summary);currentLoanDetailPanel.querySelector('[data-field="payments-rows"]').innerHTML=renderPaymentRows(loan.payments||[],loan.status);currentLoanDetailPanel.querySelectorAll('.pay-loan-btn').forEach(button=>button.addEventListener('click',event=>{event.stopPropagation();button.disabled=true;payLoanPayment(Number(button.dataset.loanPaymentId)).finally(()=>{button.disabled=false;});}));loansListPanel.after(currentLoanDetailPanel);loansListPanel.classList.add('is-hidden');currentLoanDetailPanel.scrollIntoView({behavior:'smooth',block:'start'});}
function renderLoanCard(loan,showOfferActions){const summary=getPaymentSummary(loan);let action='';if(showOfferActions){action='<div class="top-row mt-1"><button class="btn gold-btn btn-inline respond-offer-btn" type="button" data-action="accept" data-loan-id="'+Number(loan.id)+'">Aceptar</button><button class="btn blue-btn btn-inline respond-offer-btn" type="button" data-action="reject" data-loan-id="'+Number(loan.id)+'">Rechazar</button></div>';}else{action=renderPayButtons(loan,summary.payable_payments);}const interestFact=loan?.has_interest?'<p class="loan-card-fact">Interes: '+escapeHtml(getLoanInterestSummary(loan))+'</p>':'';const noteMarkup=showOfferActions?'':'<p class="loan-meta-note">'+escapeHtml(renderMemberCardNote(loan))+'</p>';return '<section class="quick-card mt-1 loan-summary-card" data-loan-id="'+Number(loan.id)+'"><div class="top-row loan-summary-top"><div><p class="quick-card-title">'+escapeHtml(loan.reason||'Prestamo')+'</p><p class="quick-card-subtitle">'+escapeHtml(getLoanStatusLabel(loan.status))+'</p><div class="loan-card-facts"><p class="loan-card-fact">Prestado: '+toMoney(loan.amount||0)+'</p>'+interestFact+'<p class="loan-card-fact">Total: '+toMoney(loan.total_amount||loan.amount)+'</p><p class="loan-card-fact">'+escapeHtml(getLoanPaymentPlanSummary(loan))+'</p></div>'+noteMarkup+'</div><button class="loan-expand-btn" type="button" aria-label="Ver detalle">v</button></div>'+action+'</section>';}
function bindLoanCards(){loansList.querySelectorAll('.loan-summary-card').forEach(card=>card.addEventListener('click',()=>{const loan=loansById.get(Number(card.dataset.loanId));if(loan){renderLoanDetail(loan);}}));loansList.querySelectorAll('.respond-offer-btn').forEach(button=>button.addEventListener('click',event=>{event.stopPropagation();button.disabled=true;respondToOffer(Number(button.dataset.loanId),button.dataset.action).finally(()=>{button.disabled=false;});}));loansList.querySelectorAll('.pay-loan-btn').forEach(button=>button.addEventListener('click',event=>{event.stopPropagation();button.disabled=true;payLoanPayment(Number(button.dataset.loanPaymentId)).finally(()=>{button.disabled=false;});}));}
async function loadLoansList(){try{const response=await apiRequest('/loans','GET');const loans=response.loans||[];loansById=new Map(loans.map(loan=>[Number(loan.id),loan]));const offered=loans.filter(loan=>loan.status==='offered');const pending=loans.filter(loan=>loan.status==='pending');const approved=loans.filter(loan=>loan.status==='approved'||loan.status==='paid');const rejected=loans.filter(loan=>loan.status==='rejected');const sections=[];if(offered.length){sections.push('<section class="quick-card"><p class="quick-card-title">Ofertas por responder</p>'+offered.map(loan=>renderLoanCard(loan,true)).join('')+'</section>');}if(pending.length){sections.push('<section class="quick-card mt-1"><p class="quick-card-title">Solicitudes pendientes</p>'+pending.map(loan=>renderLoanCard(loan,false)).join('')+'</section>');}if(approved.length){sections.push('<section class="quick-card mt-1"><p class="quick-card-title">Mis prestamos activos</p>'+approved.map(loan=>renderLoanCard(loan,false)).join('')+'</section>');}if(rejected.length){sections.push('<section class="quick-card mt-1"><p class="quick-card-title">Solicitudes rechazadas</p>'+rejected.map(loan=>renderLoanCard(loan,false)).join('')+'</section>');}if(sections.length===0){loansList.innerHTML='<section class="quick-card"><p class="quick-card-title">Sin prestamos</p><p class="quick-card-subtitle">Todavia no tienes prestamos ni solicitudes.</p><button id="empty-create-loan-btn" class="btn gold-btn btn-inline mt-1" type="button">Pedir prestamo</button></section>';document.getElementById('empty-create-loan-btn').addEventListener('click',showCreate);return;}loansList.innerHTML=sections.join('');bindLoanCards();}catch(error){loansList.innerHTML='<section class="quick-card"><p class="quick-card-title">Error</p><p class="quick-card-subtitle">'+escapeHtml(error?.data?.message||'No se pudo cargar prestamos.')+'</p></section>';}}
async function respondToOffer(loanId,action){let reason=null;if(action==='reject'){reason=window.prompt('Escribe el motivo del rechazo:');if(!reason||!reason.trim()){setFeedback('Necesitas escribir un motivo para rechazar la oferta.','feedback-error');return;}}try{const response=await apiRequest('/loans/'+loanId+'/respond','POST',{action:action,reason:reason});if(action==='accept'&&Number.isFinite(Number(response.member_balance_cents))){currentMemberBalance=Number(response.member_balance_cents);updateMemberNote();}setFeedback('Respuesta enviada correctamente.','feedback-success');await loadLoansList();}catch(error){setFeedback(error?.data?.message||'No se pudo responder la oferta.','feedback-error');}}
async function payLoanPayment(loanPaymentId){try{const response=await apiRequest('/loan-payments/'+loanPaymentId+'/pay','POST');const newBalance=Number(response.new_balance_cents);if(Number.isFinite(newBalance)){currentMemberBalance=newBalance;updateMemberNote();}else if(Number.isFinite(Number(response.member_balance_cents))){currentMemberBalance=Number(response.member_balance_cents);updateMemberNote();}if(window.handleDomusAchievements){window.handleDomusAchievements(response.achievements);}setFeedback('Pago realizado correctamente.','feedback-success');await loadLoansList();if(currentLoanDetailPanel&&response.loan){const updatedLoan=response.loan;loansById.set(Number(updatedLoan.id),updatedLoan);renderLoanDetail(updatedLoan);}}catch(error){setFeedback(error?.data?.message||'No se pudo registrar el pago.','feedback-error');}}
function getInstallmentsCount(){if(loanModeSingle.checked){return 1;}const custom=Number(customInstallments.value);if(Number.isInteger(custom)&&custom>0){return custom;}return selectedInstallments;}
function getInterestPayload(){const payload={has_interest:withInterest.checked,interest_mode:'percent',annual_interest_rate:0,fixed_interest_amount:0};if(!withInterest.checked){return payload;}const selectedValue=Number(customInterest.value)||selectedInterest;if(interestModeFixed.checked){payload.interest_mode='fixed';payload.fixed_interest_amount=Math.max(0,Math.round(selectedValue));return payload;}payload.annual_interest_rate=Math.max(0,selectedValue);return payload;}
loanForm.addEventListener('submit',async event=>{event.preventDefault();clearFeedback();const interestPayload=getInterestPayload();const payload={child_user_id:currentUserId,amount:Number(loanAmount.value),reason:loanReason.value.trim()||null,due_date:loanDueDate.value,installments_count:getInstallmentsCount(),installment_frequency:frequency.value,has_interest:interestPayload.has_interest,interest_mode:interestPayload.interest_mode,annual_interest_rate:interestPayload.annual_interest_rate,fixed_interest_amount:interestPayload.fixed_interest_amount};try{await apiRequest('/loans','POST',payload);loanForm.reset();resetLoanSelections();deferredPaymentFields.classList.add('is-hidden');interestModeRow.classList.add('is-hidden');interestOptions.classList.add('is-hidden');customInterest.classList.add('is-hidden');setFeedback('Solicitud enviada al administrador.','feedback-success');await loadLoansList();}catch(error){setFeedback(error?.data?.message||'No se pudo crear la solicitud.','feedback-error');}});
loanAmount.addEventListener('input',updateInterestOptionsUI);
loanBack.addEventListener('click',showList);
loansTabList.addEventListener('click',showList);
loansTabCreate.addEventListener('click',showCreate);
loanModeSingle.addEventListener('change',()=>{deferredPaymentFields.classList.add('is-hidden');updateLoanDateLabel();syncChoicePillState();});
loanModeDeferred.addEventListener('change',()=>{deferredPaymentFields.classList.remove('is-hidden');updateLoanDateLabel();syncChoicePillState();});
withInterest.addEventListener('change',()=>{interestModeRow.classList.toggle('is-hidden',!withInterest.checked);interestOptions.classList.toggle('is-hidden',!withInterest.checked);customInterest.classList.toggle('is-hidden',!withInterest.checked);updateInterestOptionsUI();syncChoicePillState();});
Array.from(document.querySelectorAll('.choice-pill input')).forEach(input=>input.addEventListener('change',syncChoicePillState));
Array.from(document.querySelectorAll('.interest-btn')).forEach(button=>button.addEventListener('click',()=>{selectedInterest=Number(button.dataset.rate)||0;customInterest.value='';customInterest.classList.remove('input-selected');setSelectedButton('.interest-btn',button);}));
Array.from(document.querySelectorAll('.installment-btn')).forEach(button=>button.addEventListener('click',()=>{selectedInstallments=Number(button.dataset.count)||1;customInstallments.value='';customInstallments.classList.remove('input-selected');setSelectedButton('.installment-btn',button);}));
customInstallments.addEventListener('input',()=>{const hasCustom=customInstallments.value.trim()!=='';if(hasCustom){setSelectedButton('.installment-btn',null);}else{setSelectedButton('.installment-btn',document.querySelector('.installment-btn[data-count="'+selectedInstallments+'"]'));}customInstallments.classList.toggle('input-selected',hasCustom);});
customInterest.addEventListener('input',()=>{const hasCustom=customInterest.value.trim()!=='';if(hasCustom){setSelectedButton('.interest-btn',null);}else{setSelectedButton('.interest-btn',selectedInterest?document.querySelector('.interest-btn[data-rate="'+selectedInterest+'"]'):null);}customInterest.classList.toggle('input-selected',hasCustom);});
interestModePercent.addEventListener('change',()=>{updateInterestOptionsUI();syncChoicePillState();});
interestModeFixed.addEventListener('change',()=>{updateInterestOptionsUI();syncChoicePillState();});
resetLoanSelections();
updateLoanDateLabel();
updateInterestOptionsUI();
logoutButton.addEventListener('click',async()=>{try{await apiRequest('/logout','POST');}catch(error){}finally{clearToken();window.location.href='/login';}});
menuButton.addEventListener('click',()=>{if(sidebar.classList.contains('is-open')){closeSidebar();return;}openSidebar();updateScrollHint();});
sidebarClose.addEventListener('click',closeSidebar);
sidebarOverlay.addEventListener('click',closeSidebar);
sidebarScroll.addEventListener('scroll',updateScrollHint);
window.addEventListener('resize',()=>{if(window.innerWidth>=768){closeSidebar();}updateScrollHint();});
(async function bootstrap(){updateScrollHint();if(!getToken()){window.location.href='/login';return;}try{const me=await apiRequest('/me','GET');if(me.user?.role==='parent'){window.location.href='/parent/loans';return;}if(me.user?.role!=='child'&&me.user?.role!=='member'){window.location.href='/account';return;}currentUserId=me.user.id;currentMemberBalance=Number(me.user?.balance_cents||0);sidebarUserName.textContent=me.user?.name||'Usuario';await loadSidebarLevel();updateMemberNote();await loadLoansList();showList();}catch(error){if(error.status===401){clearToken();window.location.href='/login';return;}loansList.innerHTML='<section class="quick-card"><p class="quick-card-title">Error de sesion</p><p class="quick-card-subtitle">No se pudo validar la sesion.</p></section>';}})();
</script>
</body>
</html>
