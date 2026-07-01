<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Puntos Domus Hijo</title>
    <link rel="stylesheet" href="/css/styles.css">
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
                <a class="sidebar-link" href="/member/loans"><span class="nav-icon">&bull;</span><span>Prestamos</span></a>
                <a class="sidebar-link" href="/child/savings-boxes"><span class="nav-icon">&bull;</span><span>Cajas de ahorro</span></a>
                <a class="sidebar-link" href="/child/goals"><span class="nav-icon">&bull;</span><span>Metas</span></a>
                <a class="sidebar-link" href="/child/withdrawals"><span class="nav-icon">&bull;</span><span>Retirar dinero</span></a>
                <a class="sidebar-link" href="/child/tasks"><span class="nav-icon">&bull;</span><span>Tareas</span></a>
                <a class="sidebar-link is-active" href="/child/domus-points"><span class="nav-icon">&bull;</span><span>Puntos Domus</span></a>
                <a class="sidebar-link" href="/account/education"><span class="nav-icon">&bull;</span><span>Educacion</span></a>
            </nav>
            <div id="scroll-hint" class="scroll-hint">Desliza para ver mas</div>
        </div>
    </aside>
    <section class="page content card">
        <div class="top-row"><button id="menu-btn" class="btn blue-btn menu-toggle" type="button">Menu</button><button id="logout-btn" class="btn btn-inline gold-btn right" type="button">Cerrar sesion</button></div>
        <p class="brand mt-2">DOMUS</p>
        <h1>Mis Puntos Domus</h1>
        <p class="subtitle">Junta puntos y canjealos por recompensas creadas por tu padre.</p>

        <section class="stats-grid mt-2">
            <article class="stat-card stat-card-lilac"><p class="stat-title">Disponibles</p><p id="available-points" class="stat-value">0</p><p class="stat-note">Listos para canjear</p></article>
            <article class="stat-card stat-card-mint"><p class="stat-title">Historicos</p><p id="earned-points" class="stat-value">0</p><p class="stat-note">Nunca se pierden</p></article>
            <article class="stat-card stat-card-gold"><p class="stat-title">Gastados</p><p id="spent-points" class="stat-value">0</p><p class="stat-note">En recompensas</p></article>
        </section>

        <section class="quick-card mt-2">
            <p class="quick-card-title">Mi nivel</p>
            <p id="level-name" class="quick-card-subtitle">Cargando nivel...</p>
            <p id="level-definition" class="quick-card-subtitle"></p>
        </section>

        <div id="redeem-feedback" class="feedback-box is-hidden mt-2"></div>

        <section class="mt-2">
            <p class="quick-card-title">Mis insignias Domus</p>
            <div id="badges-list" class="education-list mt-1"></div>
        </section>

        <section class="mt-2">
            <p class="quick-card-title">Recompensas disponibles</p>
            <div id="rewards-list" class="education-list mt-1"></div>
        </section>

        <section class="mt-2">
            <p class="quick-card-title">Mis canjes</p>
            <div id="redemptions-list" class="education-list mt-1"></div>
        </section>
    </section>
</main>
<script>
const API_BASE='/api',TOKEN_KEY='parent_auth_token';
const logoutButton=document.getElementById('logout-btn'),menuButton=document.getElementById('menu-btn'),sidebar=document.getElementById('sidebar'),sidebarUserName=document.getElementById('sidebar-user-name'),sidebarUserLevel=document.getElementById('sidebar-user-level'),sidebarClose=document.getElementById('sidebar-close'),sidebarOverlay=document.getElementById('sidebar-overlay'),sidebarScroll=document.getElementById('sidebar-scroll'),scrollHint=document.getElementById('scroll-hint'),availablePoints=document.getElementById('available-points'),earnedPoints=document.getElementById('earned-points'),spentPoints=document.getElementById('spent-points'),levelName=document.getElementById('level-name'),levelDefinition=document.getElementById('level-definition'),badgesList=document.getElementById('badges-list'),rewardsList=document.getElementById('rewards-list'),redemptionsList=document.getElementById('redemptions-list'),redeemFeedback=document.getElementById('redeem-feedback');
function getToken(){return localStorage.getItem(TOKEN_KEY);} function clearToken(){localStorage.removeItem(TOKEN_KEY);} function openSidebar(){sidebar.classList.add('is-open');sidebarOverlay.classList.add('is-open');} function closeSidebar(){sidebar.classList.remove('is-open');sidebarOverlay.classList.remove('is-open');}
function updateScrollHint(){const canScroll=sidebarScroll.scrollHeight>sidebarScroll.clientHeight;const nearBottom=sidebarScroll.scrollTop+sidebarScroll.clientHeight>=sidebarScroll.scrollHeight-4;scrollHint.classList.toggle('is-visible',canScroll&&!nearBottom);}
function escapeHtml(value){return String(value??'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#39;');}
function showFeedback(message,isError){redeemFeedback.textContent=message;redeemFeedback.className='feedback-box mt-2 '+(isError?'feedback-error':'feedback-success');}
async function apiRequest(path,method,payload){const token=getToken();const headers={Accept:'application/json','Content-Type':'application/json'};if(token){headers.Authorization='Bearer '+token;}const response=await fetch(API_BASE+path,{method,headers,body:payload?JSON.stringify(payload):undefined});const data=await response.json().catch(()=>({}));if(!response.ok){throw {status:response.status,data};}return data;}
async function loadUserHeader(){const me=await apiRequest('/me','GET');if(me.user?.role==='parent'){window.location.replace('/parent/domus-points');return;}sidebarUserName.textContent=me.user?.name||'Usuario';}
function renderBadges(items){if(!items.length){badgesList.innerHTML='<section class="quick-card"><p class="quick-card-subtitle">Todavia no hay insignias disponibles.</p></section>';return;}badgesList.innerHTML=items.map(badge=>'<article class="education-card"><p class="education-card-title">'+escapeHtml(badge.title)+'</p><p class="education-card-text">'+escapeHtml(badge.description||'')+'</p><div class="lesson-status-row"><span class="progress-pill">+'+Number(badge.points_reward||0)+' puntos</span><span class="progress-pill">'+(badge.is_completed?'Ganada':'Pendiente')+'</span></div></article>').join('');}
function renderRewards(items){if(!items.length){rewardsList.innerHTML='<section class="quick-card"><p class="quick-card-subtitle">Tu padre aun no ha creado recompensas.</p></section>';return;}rewardsList.innerHTML=items.map(reward=>'<article class="education-card"><p class="education-card-title">'+escapeHtml(reward.title)+'</p><p class="education-card-text">'+escapeHtml(reward.description||'Sin descripcion')+'</p><div class="lesson-status-row"><span class="progress-pill">'+Number(reward.points_cost||0)+' puntos</span>'+(reward.can_redeem?'<button class="btn gold-btn btn-inline" type="button" data-redeem-id="'+reward.id+'">Canjear</button>':'<span class="progress-pill">Te faltan puntos</span>')+'</div></article>').join('');document.querySelectorAll('[data-redeem-id]').forEach(button=>{button.addEventListener('click',()=>redeemReward(button.getAttribute('data-redeem-id')));});}
function renderRedemptions(items){if(!items.length){redemptionsList.innerHTML='<section class="quick-card"><p class="quick-card-subtitle">Todavia no has canjeado recompensas.</p></section>';return;}redemptionsList.innerHTML=items.map(item=>'<article class="education-card"><p class="education-card-title">'+escapeHtml(item.reward_title||'Recompensa')+'</p><div class="lesson-status-row"><span class="progress-pill">'+Number(item.points_spent||0)+' puntos</span><span class="progress-pill">'+escapeHtml(item.status||'redeemed')+'</span></div><p class="education-card-text">'+(item.status==='paid'?'Tu padre ya marco esta recompensa como pagada o entregada.':'Esperando a que tu padre la marque como pagada o entregada.')+'</p></article>').join('');}
async function loadData(){try{const data=await apiRequest('/child/domus-points','GET');availablePoints.textContent=Number(data.points?.available||0);earnedPoints.textContent=Number(data.points?.historical||data.points?.earned||0);spentPoints.textContent=Number(data.points?.spent||0);const resolvedLevel=data.level?('Nivel '+Number(data.level.level_number||1)+' - '+data.level.name):'Sin nivel';levelName.textContent=resolvedLevel;sidebarUserLevel.textContent=resolvedLevel;levelDefinition.textContent=data.level?.definition||'';renderBadges(data.badges||[]);renderRewards(data.rewards||[]);renderRedemptions(data.redemptions||[]);}catch(error){badgesList.innerHTML='<section class="quick-card"><p class="quick-card-title">Error</p><p class="quick-card-subtitle">No se pudieron cargar tus insignias.</p></section>';rewardsList.innerHTML='';redemptionsList.innerHTML='';levelName.textContent='No se pudo cargar el nivel.';sidebarUserLevel.textContent='Nivel no disponible';levelDefinition.textContent='';}}
async function redeemReward(rewardId){try{await apiRequest('/child/domus-points/rewards/'+rewardId+'/redeem','POST',{});showFeedback('Recompensa canjeada.',false);await loadData();}catch(error){showFeedback(error.data?.message||'No se pudo canjear la recompensa.',true);}}
logoutButton.addEventListener('click',async()=>{try{await apiRequest('/logout','POST');}catch(e){}finally{clearToken();window.location.href='/login';}});
menuButton.addEventListener('click',()=>{if(sidebar.classList.contains('is-open')){closeSidebar();return;}openSidebar();updateScrollHint();});sidebarClose.addEventListener('click',closeSidebar);sidebarOverlay.addEventListener('click',closeSidebar);sidebarScroll.addEventListener('scroll',updateScrollHint);window.addEventListener('resize',()=>{if(window.innerWidth>=768){closeSidebar();}updateScrollHint();});
updateScrollHint();loadUserHeader().then(loadData);
</script>
</body>
</html>
