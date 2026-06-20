<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cursos</title>
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
        <h1 id="page-title">Cursos</h1>
        <p id="page-subtitle" class="subtitle">Cargando categoria...</p>
        <div id="courses-content" class="mt-2"></div>
    </section>
</main>
<script>
const API_BASE='/api',TOKEN_KEY='parent_auth_token';
const logoutButton=document.getElementById('logout-btn'),menuButton=document.getElementById('menu-btn'),sidebar=document.getElementById('sidebar'),sidebarUserName=document.getElementById('sidebar-user-name'),sidebarUserLevel=document.getElementById('sidebar-user-level'),sidebarClose=document.getElementById('sidebar-close'),sidebarOverlay=document.getElementById('sidebar-overlay'),sidebarScroll=document.getElementById('sidebar-scroll'),scrollHint=document.getElementById('scroll-hint'),tasksLink=document.getElementById('tasks-link'),loansLink=document.getElementById('loans-link'),transfersLink=document.getElementById('transfers-link'),allowancesLink=document.getElementById('allowances-link'),savingsBoxesLink=document.getElementById('savings-boxes-link'),goalsLink=document.getElementById('goals-link'),domusPointsLink=document.getElementById('domus-points-link'),pageTitle=document.getElementById('page-title'),pageSubtitle=document.getElementById('page-subtitle'),coursesContent=document.getElementById('courses-content');
function getToken(){return localStorage.getItem(TOKEN_KEY);} function clearToken(){localStorage.removeItem(TOKEN_KEY);} function openSidebar(){sidebar.classList.add('is-open');sidebarOverlay.classList.add('is-open');} function closeSidebar(){sidebar.classList.remove('is-open');sidebarOverlay.classList.remove('is-open');}
function updateScrollHint(){const canScroll=sidebarScroll.scrollHeight>sidebarScroll.clientHeight;const nearBottom=sidebarScroll.scrollTop+sidebarScroll.clientHeight>=sidebarScroll.scrollHeight-4;scrollHint.classList.toggle('is-visible',canScroll&&!nearBottom);}
function escapeHtml(value){return String(value??'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#39;');}
function getCategoryId(){const match=window.location.pathname.match(/\/account\/education\/categories\/(\d+)\/courses/);return match?match[1]:null;}
async function apiRequest(path,method){const token=getToken();const headers={Accept:'application/json','Content-Type':'application/json'};if(token){headers.Authorization='Bearer '+token;}const response=await fetch(API_BASE+path,{method,headers});const data=await response.json().catch(()=>({}));if(!response.ok){throw {status:response.status,data};}return data;}
async function loadUserHeader(){const me=await apiRequest('/me','GET');const role=me.user?.role||'parent';sidebarUserName.textContent=me.user?.name||'Usuario';if(tasksLink){tasksLink.href=(role==='parent'?'/parent/tasks':'/child/tasks');}if(transfersLink){transfersLink.classList.toggle('is-hidden',role!=='parent');}if(allowancesLink){allowancesLink.classList.toggle('is-hidden',role!=='parent');}if(savingsBoxesLink){savingsBoxesLink.href=(role==='parent'?'/parent/savings-boxes':'/child/savings-boxes');savingsBoxesLink.classList.toggle('is-hidden',false);}if(goalsLink){goalsLink.href='/child/goals';goalsLink.classList.toggle('is-hidden',role==='parent');}if(loansLink){loansLink.href=(role==='parent'?'/parent/loans':'/member/loans');}if(domusPointsLink){domusPointsLink.href=(role==='parent'?'/parent/domus-points':'/child/domus-points');}if(role==='child'||role==='member'){sidebarUserLevel.style.display='block';try{const points=await apiRequest('/child/domus-points','GET');const level=points.level;sidebarUserLevel.textContent=level?('Nivel '+Number(level.level_number||1)+' - '+level.name):'Sin nivel';}catch(_error){sidebarUserLevel.textContent='Sin nivel';}}else{sidebarUserLevel.style.display='none';}}
function renderCourseExamSummary(course){const summary=course.exam_summary;if(!summary){return '';}const lastResult=summary.last_result?'<p class="education-card-text"><strong>Ultima calificacion:</strong> '+Number(summary.last_result.percentage||0)+'% ('+Number(summary.last_result.score||0)+' / '+Number(summary.last_result.total_questions||0)+').</p>':'';const message=summary.message?'<p class="education-card-text"><strong>'+escapeHtml(summary.message)+'</strong></p>':'';return lastResult+message;}
function renderCourses(data){const category=data.category||{};const courses=data.courses||[];pageTitle.textContent=category.name||'Cursos';pageSubtitle.textContent=category.description||'Selecciona un curso para empezar.';if(!courses.length){coursesContent.innerHTML='<a class="back-link" href="/account/education">Volver a categorias</a><section class="quick-card mt-2"><p class="quick-card-title">Sin cursos</p><p class="quick-card-subtitle">Esta categoria aun no tiene cursos activos.</p></section>';return;}coursesContent.innerHTML='<a class="back-link" href="/account/education">Volver a categorias</a><section class="education-list mt-2">'+courses.map(course=>'<a class="education-card" href="/account/education/courses/'+course.id+'">'+(course.image_url?'<img class="edu-image" src="'+escapeHtml(course.image_url)+'" alt="'+escapeHtml(course.title)+'" loading="lazy">':'')+'<p class="education-card-eyebrow">Curso</p><p class="education-card-title">'+escapeHtml(course.title)+'</p><p class="education-card-text">'+escapeHtml(course.description||'')+'</p>'+renderCourseExamSummary(course)+'<span class="education-card-meta">'+Number(course.lessons_count||0)+' lecciones</span></a>').join('')+'</section>';}
async function loadCourses(){const categoryId=getCategoryId();if(!getToken()){window.location.href='/login';return;}if(!categoryId){pageSubtitle.textContent='Categoria invalida.';return;}try{await loadUserHeader();const data=await apiRequest('/education/categories/'+categoryId+'/courses','GET');renderCourses(data);}catch(error){if(error.status===401){clearToken();window.location.href='/login';return;}coursesContent.innerHTML='<section class="quick-card"><p class="quick-card-title">Error</p><p class="quick-card-subtitle">No se pudieron cargar los cursos.</p></section>';}}
logoutButton.addEventListener('click',async()=>{try{await apiRequest('/logout','POST');}catch(e){}finally{clearToken();window.location.href='/login';}});
menuButton.addEventListener('click',()=>{if(sidebar.classList.contains('is-open')){closeSidebar();return;}openSidebar();updateScrollHint();});sidebarClose.addEventListener('click',closeSidebar);sidebarOverlay.addEventListener('click',closeSidebar);sidebarScroll.addEventListener('scroll',updateScrollHint);window.addEventListener('resize',()=>{if(window.innerWidth>=768){closeSidebar();}updateScrollHint();});
updateScrollHint();loadCourses();
</script>
</body>
</html>
