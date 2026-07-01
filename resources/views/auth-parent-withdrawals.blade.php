<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Retirar dinero</title>
    <link rel="stylesheet" href="/css/styles.css">
    <style>
        .withdrawal-card{display:grid;gap:.55rem;}
        .withdrawal-actions{display:flex;gap:.5rem;flex-wrap:wrap;}
        .withdrawal-actions .btn{width:auto;min-width:130px;}
        .withdrawal-muted{margin:0;color:#dbeafe;font-size:.9rem;}
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
                <a class="sidebar-link is-active" href="/parent/withdrawals"><span class="nav-icon">&bull;</span><span>Retirar dinero</span></a>
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
        <h1>Retirar dinero</h1>
        <p class="subtitle">El dinero se reserva al iniciar y se libera cuando ambos aceptan.</p>

        <section class="mt-2">
            <h3 class="quick-card-title">Mis retiros</h3>
            <div id="withdrawals-list"></div>
        </section>

        <section class="mt-2">
            <h3 class="quick-card-title">Solicitudes por aceptar</h3>
            <div id="requests-list"></div>
        </section>

        <section class="mt-2">
            <button id="start-btn" class="btn gold-btn btn-inline" type="button">Iniciar retiro</button>
            <div id="create-panel" class="quick-card mt-1 is-hidden">
                <form id="withdrawal-form" class="form">
                    <div class="field">
                        <label for="child-select">Hijo o integrante</label>
                        <select id="child-select" class="input" required></select>
                    </div>
                    <div class="field">
                        <label for="amount-input">Monto</label>
                        <input id="amount-input" class="input" type="text" inputmode="decimal" placeholder="100.00" required>
                    </div>
                    <div id="form-feedback" class="feedback-box is-hidden"></div>
                    <div class="top-row mt-2"><button id="cancel-create" class="btn blue-btn btn-inline" type="button">Volver</button><button id="submit-btn" class="btn gold-btn btn-inline" type="submit">Solicitar retiro</button></div>
                </form>
            </div>
        </section>
    </section>
</main>

<script>
const API_BASE='/api',TOKEN_KEY='parent_auth_token';
const logoutButton=document.getElementById('logout-btn'),menuButton=document.getElementById('menu-btn'),sidebar=document.getElementById('sidebar'),sidebarUserName=document.getElementById('sidebar-user-name'),sidebarClose=document.getElementById('sidebar-close'),sidebarOverlay=document.getElementById('sidebar-overlay'),sidebarScroll=document.getElementById('sidebar-scroll'),scrollHint=document.getElementById('scroll-hint'),withdrawalsList=document.getElementById('withdrawals-list'),requestsList=document.getElementById('requests-list'),startBtn=document.getElementById('start-btn'),createPanel=document.getElementById('create-panel'),withdrawalForm=document.getElementById('withdrawal-form'),childSelect=document.getElementById('child-select'),amountInput=document.getElementById('amount-input'),formFeedback=document.getElementById('form-feedback'),cancelCreate=document.getElementById('cancel-create'),submitBtn=document.getElementById('submit-btn');
function getToken(){return localStorage.getItem(TOKEN_KEY);} function clearToken(){localStorage.removeItem(TOKEN_KEY);} function openSidebar(){sidebar.classList.add('is-open');sidebarOverlay.classList.add('is-open');} function closeSidebar(){sidebar.classList.remove('is-open');sidebarOverlay.classList.remove('is-open');}
function updateScrollHint(){const canScroll=sidebarScroll.scrollHeight>sidebarScroll.clientHeight;const nearBottom=sidebarScroll.scrollTop+sidebarScroll.clientHeight>=sidebarScroll.scrollHeight-4;scrollHint.classList.toggle('is-visible',canScroll&&!nearBottom);}
function escapeHtml(value){return String(value??'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#39;');}
function formatDate(value){if(!value){return '-';}const date=new Date(value);return Number.isNaN(date.getTime())?String(value):date.toLocaleString('es-MX');}
function showFeedback(message,type){formFeedback.textContent=message;formFeedback.className='feedback-box '+type;}
function clearFeedback(){formFeedback.textContent='';formFeedback.className='feedback-box is-hidden';}
async function apiRequest(path,method,payload){const token=getToken();const headers={Accept:'application/json','Content-Type':'application/json'};if(token){headers.Authorization='Bearer '+token;}const response=await fetch(API_BASE+path,{method,headers,body:payload?JSON.stringify(payload):undefined});const data=await response.json().catch(()=>({}));if(!response.ok){throw {status:response.status,data};}return data;}
function renderEmpty(text){return '<section class="quick-card mt-1"><p class="quick-card-subtitle">'+escapeHtml(text)+'</p></section>';}
function renderCard(item,canAccept){const actions=item.status==='pending_parent'?'<div class="withdrawal-actions"><button class="btn gold-btn btn-inline" type="button" data-action="accept" data-id="'+Number(item.id)+'">Aceptar</button><button class="btn blue-btn btn-inline" type="button" data-action="cancel" data-id="'+Number(item.id)+'">Cancelar</button></div>':'';return '<section class="quick-card mt-1 withdrawal-card"><p class="quick-card-title">'+escapeHtml(item.amount_display||'$0.00')+'</p><p class="quick-card-subtitle">Integrante: '+escapeHtml(item.child?.name||'Integrante')+' | '+escapeHtml(item.status_label||item.status)+'</p><p class="withdrawal-muted">Inicio: '+escapeHtml(formatDate(item.created_at))+'</p>'+(canAccept?actions:'')+'</section>';}
function renderLists(data){const withdrawals=data.withdrawals||[],requests=data.requests||[];withdrawalsList.innerHTML=withdrawals.length?withdrawals.map(item=>renderCard(item,false)).join(''):renderEmpty('Todavia no has iniciado retiros.');requestsList.innerHTML=requests.length?requests.map(item=>renderCard(item,true)).join(''):renderEmpty('No tienes solicitudes pendientes.');}
async function loadWithdrawals(){try{const data=await apiRequest('/withdrawals','GET');renderLists(data);}catch(error){withdrawalsList.innerHTML=renderEmpty(error?.data?.message||'No se pudieron cargar los retiros.');requestsList.innerHTML='';}}
async function loadMembers(){const data=await apiRequest('/family-members','GET');const targets=data.loan_targets||[];childSelect.innerHTML=targets.length?targets.map(item=>'<option value="'+Number(item.user_id)+'">'+escapeHtml(item.name)+' (@'+escapeHtml(item.username||'sin_username')+')</option>').join(''):'<option value="">Sin integrantes</option>';submitBtn.disabled=!targets.length;}
async function acceptWithdrawal(id){await apiRequest('/withdrawals/'+id+'/accept','POST',{});await loadWithdrawals();}
async function cancelWithdrawal(id){await apiRequest('/withdrawals/'+id+'/cancel','POST',{});await loadWithdrawals();}
requestsList.addEventListener('click',async event=>{const button=event.target.closest('[data-action]');if(!button){return;}button.disabled=true;try{if(button.dataset.action==='accept'){await acceptWithdrawal(button.dataset.id);}else{await cancelWithdrawal(button.dataset.id);}}catch(error){alert(error?.data?.message||'No se pudo completar la accion.');}finally{button.disabled=false;}});
startBtn.addEventListener('click',()=>{createPanel.classList.remove('is-hidden');clearFeedback();});
cancelCreate.addEventListener('click',()=>{createPanel.classList.add('is-hidden');withdrawalForm.reset();clearFeedback();});
withdrawalForm.addEventListener('submit',async event=>{event.preventDefault();clearFeedback();submitBtn.disabled=true;try{const response=await apiRequest('/parent/withdrawals','POST',{child_user_id:childSelect.value,amount:amountInput.value.trim()});showFeedback(response.message||'Retiro iniciado.','feedback-success');withdrawalForm.reset();await loadWithdrawals();}catch(error){showFeedback(error?.data?.message||'No se pudo iniciar el retiro.','feedback-error');}finally{submitBtn.disabled=false;}});
logoutButton.addEventListener('click',async()=>{try{await apiRequest('/logout','POST');}catch(error){}finally{clearToken();window.location.href='/login';}});
menuButton.addEventListener('click',()=>{if(sidebar.classList.contains('is-open')){closeSidebar();return;}openSidebar();updateScrollHint();});
sidebarClose.addEventListener('click',closeSidebar);sidebarOverlay.addEventListener('click',closeSidebar);sidebarScroll.addEventListener('scroll',updateScrollHint);window.addEventListener('resize',()=>{if(window.innerWidth>=768){closeSidebar();}updateScrollHint();});
(async function bootstrap(){updateScrollHint();if(!getToken()){window.location.href='/login';return;}try{const me=await apiRequest('/me','GET');if(me.user?.role!=='parent'){window.location.href='/account';return;}sidebarUserName.textContent=me.user?.name||'Usuario';await loadMembers();await loadWithdrawals();}catch(error){if(error.status===401){clearToken();window.location.href='/login';return;}withdrawalsList.innerHTML=renderEmpty('No se pudo validar la sesion.');}})();
</script>
</body>
</html>
