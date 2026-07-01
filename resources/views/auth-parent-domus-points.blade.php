<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Puntos Domus Padre</title>
    <link rel="stylesheet" href="/css/styles.css">
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
                <a id="loans-link" class="sidebar-link" href="/parent/loans"><span class="nav-icon">&bull;</span><span>Prestamos</span></a>
                <a class="sidebar-link" href="/parent/transfers"><span class="nav-icon">&bull;</span><span>Dar dinero</span></a>
                <a class="sidebar-link" href="/parent/withdrawals"><span class="nav-icon">&bull;</span><span>Retirar dinero</span></a>
                <a class="sidebar-link" href="/parent/allowances"><span class="nav-icon">&bull;</span><span>Mesadas</span></a>
                <a class="sidebar-link" href="/parent/savings-boxes"><span class="nav-icon">&bull;</span><span>Cajas de ahorro</span></a>
                <a class="sidebar-link" href="/parent/tasks"><span class="nav-icon">&bull;</span><span>Tareas</span></a>
                <a class="sidebar-link is-active" href="/parent/domus-points"><span class="nav-icon">&bull;</span><span>Puntos Domus</span></a>
                <a class="sidebar-link" href="/account/education"><span class="nav-icon">&bull;</span><span>Educacion</span></a>
            </nav>
            <div id="scroll-hint" class="scroll-hint">Desliza para ver mas</div>
        </div>
    </aside>
    <section class="page content card">
        <div class="top-row"><button id="menu-btn" class="btn blue-btn menu-toggle" type="button">Menu</button><button id="logout-btn" class="btn btn-inline gold-btn right" type="button">Cerrar sesion</button></div>
        <p class="brand mt-2">DOMUS</p>
        <h1>Recompensas Domus</h1>
        <p class="subtitle">Crea recompensas simples para que tus hijos las canjeen con sus puntos.</p>

        <section class="quick-card mt-2">
            <p class="quick-card-title">Nueva recompensa</p>
            <form id="reward-form" class="form">
                <div class="field"><label for="reward-title">Titulo</label><input id="reward-title" class="input" type="text" maxlength="120" required></div>
                <div class="field"><label for="reward-description">Descripcion</label><input id="reward-description" class="input" type="text" maxlength="255"></div>
                <div class="field"><label for="reward-points">Puntos Domus</label><input id="reward-points" class="input" type="number" min="1" step="1" required></div>
                <div id="reward-feedback" class="feedback-box is-hidden"></div>
                <button class="btn gold-btn mt-2" type="submit">Guardar recompensa</button>
            </form>
        </section>

        <section class="mt-2">
            <p class="quick-card-title">Puntos de tus hijos</p>
            <div id="members-points" class="education-list mt-1"></div>
        </section>

        <section class="mt-2">
            <p class="quick-card-title">Recompensas activas</p>
            <div id="rewards-list" class="education-list mt-1"></div>
        </section>

        <section class="mt-2">
            <p class="quick-card-title">Canjes de tus hijos</p>
            <p class="choice-helper">* Cuando tu hijo canjea una recompensa, aqui solo puedes marcarla como pagada o entregada. No se cancela.</p>
            <div id="redemptions-list" class="education-list mt-1"></div>
        </section>
    </section>
</main>
<script>
const API_BASE='/api',TOKEN_KEY='parent_auth_token';
const logoutButton=document.getElementById('logout-btn'),menuButton=document.getElementById('menu-btn'),sidebar=document.getElementById('sidebar'),sidebarUserName=document.getElementById('sidebar-user-name'),sidebarClose=document.getElementById('sidebar-close'),sidebarOverlay=document.getElementById('sidebar-overlay'),sidebarScroll=document.getElementById('sidebar-scroll'),scrollHint=document.getElementById('scroll-hint'),rewardForm=document.getElementById('reward-form'),rewardFeedback=document.getElementById('reward-feedback'),membersPoints=document.getElementById('members-points'),rewardsList=document.getElementById('rewards-list'),redemptionsList=document.getElementById('redemptions-list');
function getToken(){return localStorage.getItem(TOKEN_KEY);} function clearToken(){localStorage.removeItem(TOKEN_KEY);} function openSidebar(){sidebar.classList.add('is-open');sidebarOverlay.classList.add('is-open');} function closeSidebar(){sidebar.classList.remove('is-open');sidebarOverlay.classList.remove('is-open');}
function updateScrollHint(){const canScroll=sidebarScroll.scrollHeight>sidebarScroll.clientHeight;const nearBottom=sidebarScroll.scrollTop+sidebarScroll.clientHeight>=sidebarScroll.scrollHeight-4;scrollHint.classList.toggle('is-visible',canScroll&&!nearBottom);}
function escapeHtml(value){return String(value??'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#39;');}
function showFeedback(message,isError){rewardFeedback.textContent=message;rewardFeedback.className='feedback-box '+(isError?'feedback-error':'feedback-success');}
async function apiRequest(path,method,payload){const token=getToken();const headers={Accept:'application/json','Content-Type':'application/json'};if(token){headers.Authorization='Bearer '+token;}const response=await fetch(API_BASE+path,{method,headers,body:payload?JSON.stringify(payload):undefined});const data=await response.json().catch(()=>({}));if(!response.ok){throw {status:response.status,data};}return data;}
async function loadUserHeader(){const me=await apiRequest('/me','GET');if(me.user?.role!=='parent'){window.location.replace('/child/domus-points');return;}sidebarUserName.textContent=me.user?.name||'Usuario';}
function renderMembers(items){if(!items.length){membersPoints.innerHTML='<section class="quick-card"><p class="quick-card-subtitle">Aun no tienes hijos o miembros registrados.</p></section>';return;}membersPoints.innerHTML=items.map(member=>'<article class="education-card"><p class="education-card-title">'+escapeHtml(member.name)+'</p><p class="education-card-text">Rol: '+escapeHtml(member.role||'child')+'</p><div class="lesson-status-row"><span class="progress-pill">Disponibles: '+Number(member.available_points||0)+'</span><span class="progress-pill">Ganados: '+Number(member.earned_points||0)+'</span><span class="progress-pill">Gastados: '+Number(member.spent_points||0)+'</span></div></article>').join('');}
function renderRewards(items){if(!items.length){rewardsList.innerHTML='<section class="quick-card"><p class="quick-card-subtitle">Aun no has creado recompensas.</p></section>';return;}rewardsList.innerHTML=items.map(reward=>'<article class="education-card"><p class="education-card-title">'+escapeHtml(reward.title)+'</p><p class="education-card-text">'+escapeHtml(reward.description||'Sin descripcion')+'</p><div class="lesson-status-row"><span class="progress-pill">'+Number(reward.points_cost||0)+' puntos</span><span class="progress-pill">Canjes: '+Number(reward.redemptions_count||0)+'</span></div></article>').join('');}
function renderRedemptions(items){if(!items.length){redemptionsList.innerHTML='<section class="quick-card"><p class="quick-card-subtitle">Todavia no hay canjes de recompensas.</p></section>';return;}redemptionsList.innerHTML=items.map(item=>'<article class="education-card"><p class="education-card-title">'+escapeHtml(item.reward_title||'Recompensa')+'</p><p class="education-card-text">Canjeada por: '+escapeHtml(item.child_name||'Hijo')+'</p><div class="lesson-status-row"><span class="progress-pill">'+Number(item.points_spent||0)+' puntos</span><span class="progress-pill">'+escapeHtml(item.status||'redeemed')+'</span>'+(item.status==='redeemed'?'<button class="btn gold-btn btn-inline" type="button" data-pay-redemption-id="'+item.id+'">Marcar como pagado</button>':'')+'</div></article>').join('');document.querySelectorAll('[data-pay-redemption-id]').forEach(button=>{button.addEventListener('click',()=>markRedemptionPaid(button.getAttribute('data-pay-redemption-id')));});}
async function loadData(){try{const data=await apiRequest('/parent/domus-points','GET');renderMembers(data.members||[]);renderRewards(data.rewards||[]);renderRedemptions(data.redemptions||[]);}catch(error){membersPoints.innerHTML='<section class="quick-card"><p class="quick-card-title">Error</p><p class="quick-card-subtitle">No se pudieron cargar los puntos Domus.</p></section>';rewardsList.innerHTML='';redemptionsList.innerHTML='';}}
async function markRedemptionPaid(redemptionId){try{await apiRequest('/parent/domus-points/redemptions/'+redemptionId+'/pay','POST',{});showFeedback('Recompensa marcada como pagada.',false);await loadData();}catch(error){showFeedback(error.data?.message||'No se pudo marcar el canje como pagado.',true);}}
rewardForm.addEventListener('submit',async e=>{e.preventDefault();showFeedback('Guardando...',false);const payload={title:document.getElementById('reward-title').value.trim(),description:document.getElementById('reward-description').value.trim()||null,points_cost:Number(document.getElementById('reward-points').value)};try{await apiRequest('/parent/domus-points/rewards','POST',payload);rewardForm.reset();showFeedback('Recompensa creada.',false);await loadData();}catch(error){showFeedback(error.data?.message||'No se pudo crear la recompensa.',true);}});
logoutButton.addEventListener('click',async()=>{try{await apiRequest('/logout','POST');}catch(e){}finally{clearToken();window.location.href='/login';}});
menuButton.addEventListener('click',()=>{if(sidebar.classList.contains('is-open')){closeSidebar();return;}openSidebar();updateScrollHint();});sidebarClose.addEventListener('click',closeSidebar);sidebarOverlay.addEventListener('click',closeSidebar);sidebarScroll.addEventListener('scroll',updateScrollHint);window.addEventListener('resize',()=>{if(window.innerWidth>=768){closeSidebar();}updateScrollHint();});
updateScrollHint();loadUserHeader().then(loadData);
</script>
</body>
</html>
