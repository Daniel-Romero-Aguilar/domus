<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detalle de usuario</title>
    <link rel="stylesheet" href="/css/styles.css">
</head>
<body class="app-bg">
<main class="layout">
    <div id="sidebar-overlay" class="sidebar-overlay"></div>
    <aside id="sidebar" class="sidebar">
        <button id="sidebar-close" class="sidebar-close" type="button">x</button>
        <div class="sidebar-logo-wrap"><img class="sidebar-logo" src="/img/domus_logo.png" alt="Domus logo"></div>
        <section class="profile-card"><p id="sidebar-user-name" class="profile-name">Usuario</p><a id="sidebar-user-level" class="profile-level" href="/levels" style="display:none;color:inherit;text-decoration:none;">Cargando nivel...</a></section>
        <div id="sidebar-scroll" class="sidebar-scroll">
            <nav class="sidebar-nav">
                <a class="sidebar-link" href="/account"><span class="nav-icon">&bull;</span><span>Inicio</span></a>
                <a class="sidebar-link" href="/account/users"><span class="nav-icon">&bull;</span><span>Usuarios</span></a>
                <a id="loans-link" class="sidebar-link" href="/parent/loans"><span class="nav-icon">&bull;</span><span>Prestamos</span></a>
                <a id="transfers-link" class="sidebar-link" href="/parent/transfers"><span class="nav-icon">&bull;</span><span>Dar dinero</span></a>
                <a class="sidebar-link" href="/parent/withdrawals"><span class="nav-icon">&bull;</span><span>Retirar dinero</span></a>
                <a id="allowances-link" class="sidebar-link" href="/parent/allowances"><span class="nav-icon">&bull;</span><span>Mesadas</span></a>
                <a id="savings-boxes-link" class="sidebar-link" href="/parent/savings-boxes"><span class="nav-icon">&bull;</span><span>Cajas de ahorro</span></a>
                <a id="tasks-link" class="sidebar-link" href="/parent/tasks"><span class="nav-icon">&bull;</span><span>Tareas</span></a>
                <a id="domus-points-link" class="sidebar-link" href="/parent/domus-points"><span class="nav-icon">&bull;</span><span>Puntos Domus</span></a>
                <a class="sidebar-link" href="/account/education"><span class="nav-icon">&bull;</span><span>Educacion</span></a>
            </nav>
            <div id="scroll-hint" class="scroll-hint">Desliza para ver mas</div>
        </div>
    </aside>

    <section class="page content card">
        <div class="top-row"><button id="menu-btn" class="btn blue-btn menu-toggle" type="button">Menu</button><button id="logout-btn" class="btn btn-inline gold-btn right" type="button">Cerrar sesion</button></div>
        <p class="brand mt-2">DOMUS</p>
        <div class="top-row mt-1">
            <h1 id="page-title">Detalle del usuario</h1>
            <a class="btn blue-btn btn-inline" href="/account">Volver</a>
        </div>
        <p id="page-subtitle" class="subtitle">Cargando informacion del usuario...</p>

        <section class="user-detail-grid mt-2">
            <article class="stat-card stat-card-blue">
                <p class="stat-title">Saldo disponible</p>
                <p id="member-balance" class="stat-value">$0.00</p>
                <p class="stat-note">Disponible para usar</p>
            </article>
            <article class="stat-card stat-card-gold">
                <p class="stat-title">Deuda de capital</p>
                <p id="member-debt" class="stat-value">$0.00</p>
                <p class="stat-note">Lo que falta por pagar</p>
            </article>
            <article class="stat-card stat-card-mint">
                <p class="stat-title">Puntos Domus</p>
                <p id="member-points" class="stat-value">0</p>
                <p id="member-level-note" class="stat-note">Sin nivel</p>
            </article>
        </section>

        <section class="quick-card notifications-card mt-2">
            <p class="quick-card-title">Informacion general</p>
            <div id="member-general" class="user-detail-meta">
                <p class="quick-card-subtitle">Cargando...</p>
            </div>
        </section>

        <section class="quick-card notifications-card mt-2">
            <p class="quick-card-title">Prestamos del usuario</p>
            <div id="member-loans-list" class="user-detail-loans">
                <p class="notification-empty">Cargando prestamos...</p>
            </div>
        </section>
    </section>
</main>

<script>
const API_BASE='/api',TOKEN_KEY='parent_auth_token';
const logoutButton=document.getElementById('logout-btn'),menuButton=document.getElementById('menu-btn'),sidebar=document.getElementById('sidebar'),sidebarUserName=document.getElementById('sidebar-user-name'),sidebarUserLevel=document.getElementById('sidebar-user-level'),sidebarClose=document.getElementById('sidebar-close'),sidebarOverlay=document.getElementById('sidebar-overlay'),sidebarScroll=document.getElementById('sidebar-scroll'),scrollHint=document.getElementById('scroll-hint'),tasksLink=document.getElementById('tasks-link'),loansLink=document.getElementById('loans-link'),transfersLink=document.getElementById('transfers-link'),allowancesLink=document.getElementById('allowances-link'),savingsBoxesLink=document.getElementById('savings-boxes-link'),domusPointsLink=document.getElementById('domus-points-link'),pageTitle=document.getElementById('page-title'),pageSubtitle=document.getElementById('page-subtitle'),memberBalance=document.getElementById('member-balance'),memberDebt=document.getElementById('member-debt'),memberPoints=document.getElementById('member-points'),memberLevelNote=document.getElementById('member-level-note'),memberGeneral=document.getElementById('member-general'),memberLoansList=document.getElementById('member-loans-list');
function getToken(){return localStorage.getItem(TOKEN_KEY);} function clearToken(){localStorage.removeItem(TOKEN_KEY);} function openSidebar(){sidebar.classList.add('is-open');sidebarOverlay.classList.add('is-open');} function closeSidebar(){sidebar.classList.remove('is-open');sidebarOverlay.classList.remove('is-open');}
function updateScrollHint(){const canScroll=sidebarScroll.scrollHeight>sidebarScroll.clientHeight;const nearBottom=sidebarScroll.scrollTop+sidebarScroll.clientHeight>=sidebarScroll.scrollHeight-4;scrollHint.classList.toggle('is-visible',canScroll&&!nearBottom);}
function escapeHtml(value){return String(value??'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#39;');}
function toMoneyFromCents(value){const amount=Number(value||0)/100;return '$'+amount.toLocaleString('en-US',{minimumFractionDigits:2,maximumFractionDigits:2});}
function apiRequest(path,method,payload){const token=getToken();const headers={Accept:'application/json','Content-Type':'application/json'};if(token){headers.Authorization='Bearer '+token;}return fetch(API_BASE+path,{method,headers,body:payload?JSON.stringify(payload):undefined}).then(async response=>{const data=await response.json().catch(()=>({}));if(!response.ok){throw {status:response.status,data};}return data;});}
function formatDate(value){if(!value){return 'Sin fecha';}const date=new Date(String(value).length===10?value+'T12:00:00':value);if(Number.isNaN(date.getTime())){return value;}return date.toLocaleDateString('es-MX',{year:'numeric',month:'2-digit',day:'2-digit'});}
function frequencyLabel(value){if(value==='weekly'){return 'semanales';}if(value==='biweekly'){return 'quincenales';}return 'mensuales';}
function healthBadgeClass(key){if(key==='overdue'){return 'loan-health-badge loan-health-badge-overdue';}if(key==='due-soon'){return 'loan-health-badge loan-health-badge-due-soon';}return 'loan-health-badge loan-health-badge-current';}
function loanStatusLabel(status){if(status==='paid'){return 'Liquidado';}if(status==='approved'){return 'Activo';}if(status==='offered'){return 'Ofrecido';}if(status==='pending'){return 'Pendiente';}if(status==='rejected'){return 'Rechazado';}return status||'Sin estado';}
function getUserIdFromPath(){const segments=window.location.pathname.split('/').filter(Boolean);return segments[segments.length-1]||'';}
function renderGeneral(member){memberGeneral.innerHTML='<div class="user-detail-meta-item"><span class="user-detail-meta-label">Nombre</span><strong>'+escapeHtml(member.name||'Usuario')+'</strong></div><div class="user-detail-meta-item"><span class="user-detail-meta-label">Alias</span><strong>@'+escapeHtml(member.username||'sin-username')+'</strong></div><div class="user-detail-meta-item"><span class="user-detail-meta-label">Rol</span><strong>'+escapeHtml(member.role||'child')+'</strong></div><div class="user-detail-meta-item"><span class="user-detail-meta-label">Nivel</span><strong>'+escapeHtml(member.level?('Nivel '+Number(member.level.level_number||0)):'Sin nivel')+'</strong></div><div class="user-detail-meta-item"><span class="user-detail-meta-label">Puntos historicos</span><strong>'+Number(member.points?.historical||0)+'</strong></div><div class="user-detail-meta-item"><span class="user-detail-meta-label">Puntos disponibles</span><strong>'+Number(member.points?.available||0)+'</strong></div><div class="user-detail-meta-item"><span class="user-detail-meta-label">Puntos gastados</span><strong>'+Number(member.points?.spent||0)+'</strong></div><div class="user-detail-meta-item"><span class="user-detail-meta-label">Estado del prestamo</span><strong><span class="'+healthBadgeClass(member.loan_health?.key)+'">'+escapeHtml(member.loan_health?.label||'Al dia')+'</span></strong></div>';}
function renderLoans(loans){if(!Array.isArray(loans)||!loans.length){memberLoansList.innerHTML='<p class="notification-empty">Este usuario no tiene prestamos registrados.</p>';return;}memberLoansList.innerHTML=loans.map(loan=>{const summary=loan.payment_summary||{};const nextPayment=summary.next_payment;const debt=toMoneyFromCents(summary.remaining_principal_cents||0);const total=toMoneyFromCents(Math.round(Number(loan.total_amount||0)*100));const amount=toMoneyFromCents(Math.round(Number(loan.amount||0)*100));const interest=Number(loan.has_interest)?('Si ('+Number(loan.annual_interest_rate||0).toFixed(2)+'%)'):'No';const nextLine=summary.overdue_installments>0?'Tiene '+Number(summary.overdue_installments||0)+' pago(s) vencido(s).':(nextPayment?'Proximo pago: '+formatDate(nextPayment.due_date)+'.':'Sin pagos pendientes.');return '<article class="user-loan-card"><div class="top-row"><div><p class="member-name">'+escapeHtml(loan.reason||'Prestamo sin motivo')+'</p><p class="member-meta">'+escapeHtml(loanStatusLabel(loan.status))+'</p></div><span class="member-badge">'+escapeHtml(debt)+' pendientes</span></div><div class="user-loan-grid"><div><span class="user-detail-meta-label">Prestado</span><strong>'+escapeHtml(amount)+'</strong></div><div><span class="user-detail-meta-label">Total</span><strong>'+escapeHtml(total)+'</strong></div><div><span class="user-detail-meta-label">Pagos</span><strong>'+Number(loan.installments_count||0)+' '+frequencyLabel(loan.installment_frequency||'monthly')+'</strong></div><div><span class="user-detail-meta-label">Primer pago</span><strong>'+escapeHtml(formatDate(loan.due_date))+'</strong></div><div><span class="user-detail-meta-label">Intereses</span><strong>'+escapeHtml(interest)+'</strong></div><div><span class="user-detail-meta-label">Capital restante</span><strong>'+escapeHtml(debt)+'</strong></div></div><p class="quick-card-subtitle">'+escapeHtml(nextLine)+'</p></article>';}).join('');}
async function loadUserHeader(){if(!getToken()){window.location.href='/login';return;}const data=await apiRequest('/me','GET');if(data.user?.role!=='parent'){window.location.replace('/account');return;}sidebarUserName.textContent=data.user?.name||'Usuario';sidebarUserLevel.style.display='none';}
async function loadPage(){if(!getToken()){window.location.href='/login';return;}try{await loadUserHeader();const userId=getUserIdFromPath();const data=await apiRequest('/family-members/'+userId+'/summary','GET');const member=data.member||{};pageTitle.textContent=member.name?('Resumen de '+member.name):'Detalle del usuario';pageSubtitle.textContent='Aqui puedes ver su saldo, su nivel y el estado de sus prestamos.';memberBalance.textContent=member.balance_display||'$0.00';memberDebt.textContent=member.debt_principal_display||'$0.00';memberPoints.textContent=Number(member.points?.available||0);memberLevelNote.textContent=member.level?('Nivel '+Number(member.level.level_number||0)+' - '+member.level.name):'Sin nivel';renderGeneral(member);renderLoans(data.loans||[]);}catch(error){if(error.status===401){clearToken();window.location.href='/login';return;}pageTitle.textContent='No se pudo cargar el usuario';pageSubtitle.textContent=error?.data?.message||'Intenta de nuevo mas tarde.';memberGeneral.innerHTML='<p class="quick-card-subtitle">No fue posible obtener la informacion del usuario.</p>';memberLoansList.innerHTML='';}}
logoutButton.addEventListener('click',async()=>{try{await apiRequest('/logout','POST');}catch(e){}finally{clearToken();window.location.href='/login';}});
menuButton.addEventListener('click',()=>{if(sidebar.classList.contains('is-open')){closeSidebar();return;}openSidebar();updateScrollHint();});sidebarClose.addEventListener('click',closeSidebar);sidebarOverlay.addEventListener('click',closeSidebar);sidebarScroll.addEventListener('scroll',updateScrollHint);window.addEventListener('resize',()=>{if(window.innerWidth>=768){closeSidebar();}updateScrollHint();});
updateScrollHint();loadPage();
</script>
</body>
</html>
