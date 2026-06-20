<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Educacion</title>
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
                <a id="loans-link" class="sidebar-link" href="/account/loans"><span class="nav-icon">&bull;</span><span>Prestamos</span></a>
                <a id="transfers-link" class="sidebar-link" href="/parent/transfers"><span class="nav-icon">&bull;</span><span>Dar dinero</span></a>
                <a id="allowances-link" class="sidebar-link" href="/parent/allowances"><span class="nav-icon">&bull;</span><span>Mesadas</span></a>
                <a id="savings-boxes-link" class="sidebar-link" href="/parent/savings-boxes"><span class="nav-icon">&bull;</span><span>Cajas de ahorro</span></a>
                <a id="goals-link" class="sidebar-link" href="/child/goals"><span class="nav-icon">&bull;</span><span>Metas</span></a>
                <a id="tasks-link" class="sidebar-link" href="/child/tasks"><span class="nav-icon">&bull;</span><span>Tareas</span></a>
                <a id="domus-points-link" class="sidebar-link" href="/account/domus-points"><span class="nav-icon">&bull;</span><span>Puntos Domus</span></a>
                <a class="sidebar-link is-active" href="/account/education"><span class="nav-icon">&bull;</span><span>Educacion</span></a>
            </nav>
            <div id="scroll-hint" class="scroll-hint">Desliza para ver mas</div>
        </div>
    </aside>
    <section class="page content card">
        <div class="top-row"><button id="menu-btn" class="btn blue-btn menu-toggle" type="button">Menu</button><button id="logout-btn" class="btn btn-inline gold-btn right" type="button">Cerrar sesion</button></div>
        <p class="brand mt-2">DOMUS</p>
        <h1>Educacion</h1>
        <p class="subtitle">Explora categorias y cursos cortos para aprender a usar mejor tu dinero.</p>
        <div id="education-content" class="mt-2"></div>
    </section>
</main>
<script>
const API_BASE='/api',TOKEN_KEY='parent_auth_token';
const logoutButton=document.getElementById('logout-btn'),menuButton=document.getElementById('menu-btn'),sidebar=document.getElementById('sidebar'),sidebarUserName=document.getElementById('sidebar-user-name'),sidebarUserLevel=document.getElementById('sidebar-user-level'),sidebarClose=document.getElementById('sidebar-close'),sidebarOverlay=document.getElementById('sidebar-overlay'),sidebarScroll=document.getElementById('sidebar-scroll'),scrollHint=document.getElementById('scroll-hint'),educationContent=document.getElementById('education-content'),tasksLink=document.getElementById('tasks-link'),loansLink=document.getElementById('loans-link'),transfersLink=document.getElementById('transfers-link'),allowancesLink=document.getElementById('allowances-link'),savingsBoxesLink=document.getElementById('savings-boxes-link'),goalsLink=document.getElementById('goals-link'),domusPointsLink=document.getElementById('domus-points-link');
function getToken(){return localStorage.getItem(TOKEN_KEY);} function clearToken(){localStorage.removeItem(TOKEN_KEY);} function openSidebar(){sidebar.classList.add('is-open');sidebarOverlay.classList.add('is-open');} function closeSidebar(){sidebar.classList.remove('is-open');sidebarOverlay.classList.remove('is-open');}
function updateScrollHint(){const canScroll=sidebarScroll.scrollHeight>sidebarScroll.clientHeight;const nearBottom=sidebarScroll.scrollTop+sidebarScroll.clientHeight>=sidebarScroll.scrollHeight-4;scrollHint.classList.toggle('is-visible',canScroll&&!nearBottom);}
function escapeHtml(value){return String(value??'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#39;');}
async function apiRequest(path,method){const token=getToken();const headers={Accept:'application/json','Content-Type':'application/json'};if(token){headers.Authorization='Bearer '+token;}const response=await fetch(API_BASE+path,{method,headers});const data=await response.json().catch(()=>({}));if(!response.ok){throw {status:response.status,data};}return data;}
async function loadUserHeader(){const me=await apiRequest('/me','GET');const role=me.user?.role||'parent';sidebarUserName.textContent=me.user?.name||'Usuario';if(tasksLink){tasksLink.href=(role==='parent'?'/parent/tasks':'/child/tasks');}if(transfersLink){transfersLink.classList.toggle('is-hidden',role!=='parent');}if(allowancesLink){allowancesLink.classList.toggle('is-hidden',role!=='parent');}if(savingsBoxesLink){savingsBoxesLink.href=(role==='parent'?'/parent/savings-boxes':'/child/savings-boxes');savingsBoxesLink.classList.toggle('is-hidden',false);}if(goalsLink){goalsLink.href='/child/goals';goalsLink.classList.toggle('is-hidden',role==='parent');}if(loansLink){loansLink.href=(role==='parent'?'/parent/loans':'/member/loans');}if(domusPointsLink){domusPointsLink.href=(role==='parent'?'/parent/domus-points':'/child/domus-points');}if(role==='child'||role==='member'){sidebarUserLevel.style.display='block';try{const points=await apiRequest('/child/domus-points','GET');const level=points.level;sidebarUserLevel.textContent=level?('Nivel '+Number(level.level_number||1)+' - '+level.name):'Sin nivel';}catch(_error){sidebarUserLevel.textContent='Sin nivel';}}else{sidebarUserLevel.style.display='none';}}
function renderCategories(data){const hero=data.hero||{};const categories=data.categories||[];if(!categories.length){educationContent.innerHTML='<section class="quick-card"><p class="quick-card-title">Sin categorias</p><p class="quick-card-subtitle">No hay contenido disponible por ahora.</p></section>';return;}educationContent.innerHTML='<section class="quick-card education-hero"><p class="quick-card-title">'+escapeHtml(hero.title||'Aprende finanzas')+'</p><p class="quick-card-subtitle">'+escapeHtml(hero.text||'Conoce lo basico para tomar mejores decisiones con tu dinero.')+'</p></section><section class="education-list mt-2">'+categories.map(category=>'<a class="education-card" href="/account/education/categories/'+category.id+'/courses"><p class="education-card-eyebrow">Categoria</p><p class="education-card-title">'+escapeHtml(category.name)+'</p><p class="education-card-text">'+escapeHtml(category.description||'')+'</p><p class="education-card-text"><strong>Gana hasta '+Number(category.max_domus_points||0)+' puntos Domus</strong></p><span class="education-card-meta">'+Number(category.courses_count||0)+' curso(s)</span></a>').join('')+'</section>';}
async function loadEducation(){if(!getToken()){window.location.href='/login';return;}try{await loadUserHeader();const data=await apiRequest('/education/categories','GET');renderCategories(data);}catch(error){if(error.status===401){clearToken();window.location.href='/login';return;}educationContent.innerHTML='<section class="quick-card"><p class="quick-card-title">Error</p><p class="quick-card-subtitle">No se pudo cargar educacion.</p></section>';}}
logoutButton.addEventListener('click',async()=>{try{await apiRequest('/logout','POST');}catch(e){}finally{clearToken();window.location.href='/login';}});
menuButton.addEventListener('click',()=>{if(sidebar.classList.contains('is-open')){closeSidebar();return;}openSidebar();updateScrollHint();});sidebarClose.addEventListener('click',closeSidebar);sidebarOverlay.addEventListener('click',closeSidebar);sidebarScroll.addEventListener('scroll',updateScrollHint);window.addEventListener('resize',()=>{if(window.innerWidth>=768){closeSidebar();}updateScrollHint();});
updateScrollHint();loadEducation();
</script>
</body>
</html>
