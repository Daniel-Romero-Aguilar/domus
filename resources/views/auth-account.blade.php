<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Account</title>
    <link rel="stylesheet" href="/css/styles.css">
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
                <a class="sidebar-link is-active" href="/account"><span class="nav-icon">•</span><span>Inicio</span></a>
                <a class="sidebar-link" href="/account/users"><span class="nav-icon">•</span><span>Usuarios</span></a>
                <a id="loans-link" class="sidebar-link" href="/account/loans"><span class="nav-icon">•</span><span>Prestamos</span></a>
                <a id="tasks-link" class="sidebar-link" href="/child/tasks"><span class="nav-icon">•</span><span>Tareas</span></a><a class="sidebar-link" href="/account/education"><span class="nav-icon">•</span><span>Educacion</span></a>
            </nav>
            <div id="scroll-hint" class="scroll-hint">Desliza para ver mas</div>
        </div>
    </aside>

    <section class="page content card">
        <div class="top-row"><button id="menu-btn" class="btn blue-btn menu-toggle" type="button">Menu</button><button id="logout-btn" class="btn btn-inline gold-btn right" type="button">Cerrar sesion</button></div>
        <p class="brand mt-2">DOMUS</p><h1 id="greeting">Hola</h1><p class="subtitle">Aqui tienes un resumen general de tu hogar financiero.</p>
        <section class="stats-grid mt-2">
            <article class="stat-card stat-card-blue"><p class="stat-title">Dinero disponible</p><p id="available-balance" class="stat-value">$0.00</p><p class="stat-note">Disponible en caja</p><button id="add-money-btn" class="btn gold-btn mt-1" type="button">Agregar dinero</button></article>
            <article class="stat-card stat-card-gold"><p id="stat-2-title" class="stat-title">Total prestado</p><p id="stat-2-value" class="stat-value">$0.00</p><p id="stat-2-note" class="stat-note">En prestamos activos</p></article>
            <article class="stat-card stat-card-mint"><p id="stat-3-title" class="stat-title">Intereses generados</p><p id="stat-3-value" class="stat-value">$2,125.50</p><p id="stat-3-note" class="stat-note">Este mes</p></article>
            <article id="domus-points-card" class="stat-card stat-card-lilac is-hidden"><p class="stat-title">Puntos DOMUS</p><p class="stat-value">1,250</p><p class="stat-note">Nivel 3</p></article>
        </section>
        <section id="new-loan-card" class="quick-card mt-2">
            <p class="quick-card-title">Nuevo prestamo</p>
            <p class="quick-card-subtitle">Otorga un prestamo o credito a un miembro de tu familia.</p>
            <a id="new-loan-link" class="btn gold-btn btn-inline quick-card-action" href="/parent/loans">Nuevo prestamo</a>
        </section>
        <pre id="output" class="result mt-2">Cargando...</pre>
    </section>
</main>

<div id="money-modal" class="modal-overlay">
    <section class="modal-card"><div class="top-row"><h2 class="modal-title">Agregar dinero</h2><button id="money-modal-close" class="sidebar-close modal-close" type="button">x</button></div><p class="subtitle">Ingresa un monto entero positivo.</p><form id="money-form" class="form"><div class="field"><label for="money-amount">Monto</label><input id="money-amount" class="input" type="number" min="1" step="1" required></div><div class="modal-actions mt-2"><button id="money-cancel" class="btn blue-btn btn-inline" type="button">Cancelar</button><button class="btn gold-btn btn-inline" type="submit">Guardar</button></div></form></section>
</div>

<script>
const API_BASE='/api',TOKEN_KEY='parent_auth_token';
const greeting=document.getElementById('greeting'),output=document.getElementById('output'),availableBalance=document.getElementById('available-balance'),addMoneyButton=document.getElementById('add-money-btn'),logoutButton=document.getElementById('logout-btn'),menuButton=document.getElementById('menu-btn'),sidebar=document.getElementById('sidebar'),sidebarUserName=document.getElementById('sidebar-user-name'),sidebarClose=document.getElementById('sidebar-close'),sidebarOverlay=document.getElementById('sidebar-overlay'),sidebarScroll=document.getElementById('sidebar-scroll'),scrollHint=document.getElementById('scroll-hint'),moneyModal=document.getElementById('money-modal'),moneyForm=document.getElementById('money-form'),moneyAmountInput=document.getElementById('money-amount'),moneyModalClose=document.getElementById('money-modal-close'),moneyCancel=document.getElementById('money-cancel'),stat2Title=document.getElementById('stat-2-title'),stat2Value=document.getElementById('stat-2-value'),stat2Note=document.getElementById('stat-2-note'),stat3Title=document.getElementById('stat-3-title'),stat3Value=document.getElementById('stat-3-value'),stat3Note=document.getElementById('stat-3-note'),domusPointsCard=document.getElementById('domus-points-card'),newLoanCard=document.getElementById('new-loan-card'),tasksLink=document.getElementById('tasks-link'),loansLink=document.getElementById('loans-link'),newLoanLink=document.getElementById('new-loan-link');
function getToken(){return localStorage.getItem(TOKEN_KEY);} function clearToken(){localStorage.removeItem(TOKEN_KEY);} function openSidebar(){sidebar.classList.add('is-open');sidebarOverlay.classList.add('is-open');} function closeSidebar(){sidebar.classList.remove('is-open');sidebarOverlay.classList.remove('is-open');}
function openMoneyModal(){moneyModal.classList.add('is-open');moneyAmountInput.value='';setTimeout(()=>moneyAmountInput.focus(),10);} function closeMoneyModal(){moneyModal.classList.remove('is-open');}
function updateScrollHint(){const canScroll=sidebarScroll.scrollHeight>sidebarScroll.clientHeight;const nearBottom=sidebarScroll.scrollTop+sidebarScroll.clientHeight>=sidebarScroll.scrollHeight-4;scrollHint.classList.toggle('is-visible',canScroll&&!nearBottom);} 
async function apiRequest(path,method,payload){const token=getToken();const headers={Accept:'application/json','Content-Type':'application/json'};if(token){headers.Authorization='Bearer '+token;}const response=await fetch(API_BASE+path,{method,headers,body:payload?JSON.stringify(payload):undefined});const data=await response.json().catch(()=>({}));if(!response.ok){throw {status:response.status,data};}return data;}
async function loadUser(){if(!getToken()){window.location.href='/login';return;}try{const data=await apiRequest('/me','GET');const name=data.user?.name||'';const role=data.user?.role||'parent';const loansUrl=role==='parent'?'/parent/loans':'/member/loans';if(tasksLink){tasksLink.href=(role==='parent'?'/parent/tasks':'/child/tasks');}if(loansLink){loansLink.href=loansUrl;}if(newLoanLink){newLoanLink.href=loansUrl;}const balanceAmount=data.user?.balance?Number(data.user.balance.amount):0;greeting.textContent='Hola '+name;sidebarUserName.textContent=name||'Usuario';availableBalance.textContent='$'+balanceAmount.toLocaleString('en-US',{minimumFractionDigits:2,maximumFractionDigits:2});if(role==='child'){stat2Title.textContent='Deuda total';stat2Value.textContent='$1,150.00';stat2Note.textContent='En 2 prestamos';stat3Title.textContent='Ahorros totales';stat3Value.textContent='$980.00';stat3Note.textContent='Sigue creciendo';domusPointsCard.classList.remove('is-hidden');newLoanCard.classList.add('is-hidden');}else{stat2Title.textContent='Total prestado';try{const activeTotals=await apiRequest('/loans/active-total','GET');const totalPaid=Number(activeTotals.estimated_total_paid||0);const activeCount=Number(activeTotals.total_active_loans||0);stat2Value.textContent='$'+totalPaid.toLocaleString('en-US',{minimumFractionDigits:2,maximumFractionDigits:2});stat2Note.textContent='En '+activeCount+' prestamos activos';}catch(_e){stat2Value.textContent='$0.00';stat2Note.textContent='En prestamos activos';}stat3Title.textContent='Intereses generados';stat3Value.textContent='$2,125.50';stat3Note.textContent='Este mes';domusPointsCard.classList.add('is-hidden');newLoanCard.classList.remove('is-hidden');}output.textContent=JSON.stringify(data,null,2);}catch(error){output.textContent=JSON.stringify(error,null,2);if(error.status===401){clearToken();window.location.href='/login';}}}
logoutButton.addEventListener('click',async()=>{try{await apiRequest('/logout','POST');}catch(e){}finally{clearToken();window.location.href='/login';}});
menuButton.addEventListener('click',()=>{if(sidebar.classList.contains('is-open')){closeSidebar();return;}openSidebar();updateScrollHint();}); sidebarClose.addEventListener('click',closeSidebar); sidebarOverlay.addEventListener('click',closeSidebar); sidebarScroll.addEventListener('scroll',updateScrollHint);
addMoneyButton.addEventListener('click',openMoneyModal); moneyModalClose.addEventListener('click',closeMoneyModal); moneyCancel.addEventListener('click',closeMoneyModal); moneyModal.addEventListener('click',e=>{if(e.target===moneyModal){closeMoneyModal();}});
moneyForm.addEventListener('submit',async e=>{e.preventDefault();const amount=Number(moneyAmountInput.value);if(!Number.isInteger(amount)||amount<=0){output.textContent=JSON.stringify({message:'Solo se aceptan enteros positivos.'},null,2);return;}try{const response=await apiRequest('/balance/add','POST',{amount});const newBalance=Number(response.data.balance);availableBalance.textContent='$'+newBalance.toLocaleString('en-US',{minimumFractionDigits:2,maximumFractionDigits:2});output.textContent=JSON.stringify(response,null,2);closeMoneyModal();}catch(error){output.textContent=JSON.stringify(error,null,2);}});
window.addEventListener('resize',()=>{if(window.innerWidth>=768){closeSidebar();}updateScrollHint();}); updateScrollHint(); loadUser();
</script>
</body>
</html>




