<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Niveles Domus</title>
    <link rel="stylesheet" href="/css/styles.css">
</head>
<body class="app-bg">
<main class="layout">
    <div id="sidebar-overlay" class="sidebar-overlay"></div>
    <aside id="sidebar" class="sidebar">
        <button id="sidebar-close" class="sidebar-close" type="button">x</button>
        <div class="sidebar-logo-wrap"><img class="sidebar-logo" src="/img/domus_logo.png" alt="Domus logo"></div>
        <section class="profile-card">
            <p id="sidebar-user-name" class="profile-name">Usuario</p>
            <a id="sidebar-user-level" class="profile-level" href="/levels" style="display:block;color:inherit;text-decoration:none;">Cargando nivel...</a>
        </section>
        <div id="sidebar-scroll" class="sidebar-scroll">
            <nav class="sidebar-nav">
                <a class="sidebar-link" href="/account"><span class="nav-icon">&bull;</span><span>Inicio</span></a>
                <a class="sidebar-link" href="/member/loans"><span class="nav-icon">&bull;</span><span>Prestamos</span></a>
                <a class="sidebar-link" href="/child/savings-boxes"><span class="nav-icon">&bull;</span><span>Cajas de ahorro</span></a>
                <a class="sidebar-link" href="/child/goals"><span class="nav-icon">&bull;</span><span>Metas</span></a>
                <a class="sidebar-link" href="/child/withdrawals"><span class="nav-icon">&bull;</span><span>Retirar dinero</span></a>
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
        <h1>Niveles Domus</h1>
        <p class="subtitle">Conoce los niveles que puedes alcanzar con tus puntos historicos acumulados.</p>
        <div id="current-level-card" class="quick-card mt-2">
            <p class="quick-card-title">Tu nivel actual</p>
            <p id="current-level-name" class="quick-card-subtitle">Cargando...</p>
            <p id="current-level-definition" class="quick-card-subtitle"></p>
        </div>
        <section class="mt-2">
            <p class="quick-card-title">Lista de niveles</p>
            <div id="levels-list" class="education-list mt-1"></div>
        </section>
    </section>
</main>
<script>
const API_BASE='/api',TOKEN_KEY='parent_auth_token';
const logoutButton=document.getElementById('logout-btn'),menuButton=document.getElementById('menu-btn'),sidebar=document.getElementById('sidebar'),sidebarUserName=document.getElementById('sidebar-user-name'),sidebarUserLevel=document.getElementById('sidebar-user-level'),sidebarClose=document.getElementById('sidebar-close'),sidebarOverlay=document.getElementById('sidebar-overlay'),sidebarScroll=document.getElementById('sidebar-scroll'),scrollHint=document.getElementById('scroll-hint'),currentLevelName=document.getElementById('current-level-name'),currentLevelDefinition=document.getElementById('current-level-definition'),levelsList=document.getElementById('levels-list');
function getToken(){return localStorage.getItem(TOKEN_KEY);} function clearToken(){localStorage.removeItem(TOKEN_KEY);} function openSidebar(){sidebar.classList.add('is-open');sidebarOverlay.classList.add('is-open');} function closeSidebar(){sidebar.classList.remove('is-open');sidebarOverlay.classList.remove('is-open');}
function updateScrollHint(){const canScroll=sidebarScroll.scrollHeight>sidebarScroll.clientHeight;const nearBottom=sidebarScroll.scrollTop+sidebarScroll.clientHeight>=sidebarScroll.scrollHeight-4;scrollHint.classList.toggle('is-visible',canScroll&&!nearBottom);}
function escapeHtml(value){return String(value??'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#39;');}
async function apiRequest(path,method){const token=getToken();const headers={Accept:'application/json','Content-Type':'application/json'};if(token){headers.Authorization='Bearer '+token;}const response=await fetch(API_BASE+path,{method,headers});const data=await response.json().catch(()=>({}));if(!response.ok){throw {status:response.status,data};}return data;}
function renderLevels(levels,currentLevel){if(!levels.length){levelsList.innerHTML='<section class="quick-card"><p class="quick-card-subtitle">No hay niveles disponibles por ahora.</p></section>';return;}levelsList.innerHTML=levels.map(level=>'<article class="education-card'+(currentLevel&&Number(currentLevel.level_number)===Number(level.level_number)?' is-active':'')+'"><p class="education-card-eyebrow">Nivel '+Number(level.level_number||0)+'</p><p class="education-card-title">'+escapeHtml(level.name)+'</p><p class="education-card-text">'+escapeHtml(level.definition||'')+'</p><span class="education-card-meta">Desde '+Number(level.min_points||0)+' puntos</span></article>').join('');}
async function loadPage(){if(!getToken()){window.location.href='/login';return;}try{const me=await apiRequest('/me','GET');if(me.user?.role==='parent'){window.location.replace('/account');return;}sidebarUserName.textContent=me.user?.name||'Usuario';const [levelsData,pointsData]=await Promise.all([apiRequest('/domus-levels','GET'),apiRequest('/child/domus-points','GET')]);const level=pointsData.level||null;const resolvedLevel=level?('Nivel '+Number(level.level_number||1)+' - '+level.name):'Sin nivel';sidebarUserLevel.textContent=resolvedLevel;currentLevelName.textContent=resolvedLevel;currentLevelDefinition.textContent=level?.definition||'';renderLevels(levelsData.levels||[],level);}catch(error){if(error.status===401){clearToken();window.location.href='/login';return;}currentLevelName.textContent='No se pudieron cargar los niveles.';currentLevelDefinition.textContent='';levelsList.innerHTML='<section class="quick-card"><p class="quick-card-subtitle">Intenta de nuevo mas tarde.</p></section>';}}
logoutButton.addEventListener('click',async()=>{try{await apiRequest('/logout','POST');}catch(e){}finally{clearToken();window.location.href='/login';}});
menuButton.addEventListener('click',()=>{if(sidebar.classList.contains('is-open')){closeSidebar();return;}openSidebar();updateScrollHint();});sidebarClose.addEventListener('click',closeSidebar);sidebarOverlay.addEventListener('click',closeSidebar);sidebarScroll.addEventListener('scroll',updateScrollHint);window.addEventListener('resize',()=>{if(window.innerWidth>=768){closeSidebar();}updateScrollHint();});
updateScrollHint();loadPage();
</script>
</body>
</html>
