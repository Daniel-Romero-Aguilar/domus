<!DOCTYPE html>
<html lang="es-MX">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="DOMUS ayuda a madres, padres e hijos a aprender finanzas con metas, decisiones y hábitos que se practican en familia.">
    <meta name="theme-color" content="#0D1B3D">
    <meta property="og:title" content="DOMUS | Educación financiera en familia">
    <meta property="og:description" content="Pequeños hábitos hoy. Grandes decisiones mañana.">
    <meta property="og:image" content="{{ asset('img/landing/domi-family-guide.webp') }}">
    <meta property="og:type" content="website">
    <title>DOMUS | Educación financiera en familia</title>
    <link rel="stylesheet" href="{{ asset('css/landing.css') }}">
    <script src="{{ asset('js/landing.js') }}" defer></script>
</head>
<body class="domus-landing">
    <a class="dl-skip-link" href="#contenido">Ir al contenido</a>

    <header class="dl-header" data-dl-header>
        <div class="dl-container dl-header__inner">
            <a class="dl-brand" href="#inicio" aria-label="DOMUS, volver al inicio">
                <img class="dl-brand__image" src="{{ asset('img/domus_logo.png') }}" alt="DOMUS">
            </a>

            <button
                class="dl-menu-button"
                type="button"
                aria-expanded="false"
                aria-controls="dl-navigation"
                aria-label="Abrir menú"
                data-dl-menu-button
            >
                <span></span>
                <span></span>
                <span></span>
            </button>

            <nav class="dl-navigation" id="dl-navigation" aria-label="Navegación principal" data-dl-navigation>
                    <a href="#benefits-intro">¿Por qué DOMUS?</a>
                    <a href="#how-intro">Cómo funciona</a>
                    <a href="#domi-intro">Conoce a Domi</a>
                <a class="dl-button dl-button--small dl-button--gold" href="#unete">Quiero conocerlo</a>
            </nav>
        </div>
    </header>

    <main id="contenido">
        <section class="dl-hero" id="inicio" aria-labelledby="hero-title" data-dl-spotlight>
            <span class="dl-sparkle dl-sparkle--one" aria-hidden="true">✦</span>
            <span class="dl-sparkle dl-sparkle--two" aria-hidden="true">✦</span>
            <span class="dl-orbit dl-orbit--one" aria-hidden="true"></span>
            <span class="dl-orbit dl-orbit--two" aria-hidden="true"></span>

            <div class="dl-container dl-hero__grid">
                <div class="dl-hero__copy dl-reveal">
                    <span class="dl-eyebrow">
                        <span aria-hidden="true">✦</span>
                        Educación financiera que empieza en casa
                    </span>
                    <h1 class="dl-h1" id="hero-title">
                        Pequeños hábitos hoy.
                        <span>Grandes decisiones mañana.</span>
                    </h1>
                    <p class="dl-p1 dl-hero__lead">
                        DOMUS convierte el ahorro, las metas y las decisiones cotidianas en momentos para aprender juntos.
                    </p>
                    <div class="dl-hero__actions">
                        <a class="dl-button dl-button--gold dl-button--shine" href="#unete">
                            Quiero ser de las primeras familias
                            <span aria-hidden="true">→</span>
                        </a>
                        <a class="dl-text-link" href="#como-funciona">
                            Mira cómo funciona
                            <span aria-hidden="true">↓</span>
                        </a>
                    </div>
                    <div class="dl-trust-row" aria-label="Características principales">
                        <span>
                            <svg viewBox="0 0 24 24" aria-hidden="true">
                                <path d="m8 12 2.6 2.6L16.5 8.7"></path>
                                <circle cx="12" cy="12" r="9"></circle>
                            </svg>
                            Aprendizaje en familia
                        </span>
                        <span>
                            <svg viewBox="0 0 24 24" aria-hidden="true">
                                <path d="m8 12 2.6 2.6L16.5 8.7"></path>
                                <circle cx="12" cy="12" r="9"></circle>
                            </svg>
                            Hábitos, no sermones
                        </span>
                    </div>
                </div>

                <div class="dl-hero__visual dl-reveal" aria-label="Domi acompaña a tu familia a alcanzar sus metas">
                    <div class="dl-hero-card">
                        <div class="dl-hero-card__glow" aria-hidden="true"></div>
                        <img
                            src="{{ asset('img/landing/domi-family-guide.webp') }}"
                            alt="Domi, el búho guía de DOMUS, junto a una alcancía y una gráfica de progreso"
                            width="1200"
                            height="1200"
                        >
                    </div>
                    <div class="dl-float-card dl-float-card--goal">
                        <span class="dl-float-card__icon dl-float-card__icon--pink" aria-hidden="true">◎</span>
                        <span>
                            <small>Meta familiar</small>
                            <strong>Mi primera bici</strong>
                        </span>
                    </div>
                    <div class="dl-float-card dl-float-card--progress">
                        <span class="dl-float-card__icon dl-float-card__icon--green" aria-hidden="true">✓</span>
                        <span>
                            <small>Nuevo hábito</small>
                            <strong>¡3 semanas ahorrando!</strong>
                        </span>
                    </div>
                </div>
            </div>
        </section>

        <section class="dl-section dl-benefits" id="beneficios" aria-labelledby="benefits-title">
            <div class="dl-container">
                <div class="dl-section-heading dl-section-heading--center dl-reveal" id="benefits-intro">
                    <span class="dl-kicker">Aprender se vuelve parte del día</span>
                    <h2 class="dl-h2" id="benefits-title">No es otra clase. Es la vida real, explicada fácil.</h2>
                    <p class="dl-p2">Cada decisión cotidiana puede convertirse en una lección que sí se recuerda.</p>
                </div>

                <div class="dl-benefit-grid">
                    <article class="dl-benefit-card dl-benefit-card--blue dl-reveal">
                        <span class="dl-icon-box" aria-hidden="true">
                            <svg viewBox="0 0 24 24">
                                <path d="M6 8.5h12v9H6z"></path>
                                <path d="M9 8.5V7a3 3 0 0 1 6 0v1.5M9 13h6"></path>
                            </svg>
                        </span>
                        <h3 class="dl-h5">Ahorrar con intención</h3>
                        <p class="dl-p3">Transformen un “lo quiero” en una meta visible y un plan alcanzable.</p>
                        <span class="dl-card-tag">Metas + constancia</span>
                    </article>

                    <article class="dl-benefit-card dl-benefit-card--purple dl-reveal">
                        <span class="dl-icon-box" aria-hidden="true">
                            <svg viewBox="0 0 24 24">
                                <path d="M12 3v18M7 7.5h7.5a3 3 0 0 1 0 6H9.5a3 3 0 0 0 0 6H17"></path>
                            </svg>
                        </span>
                        <h3 class="dl-h5">Decidir con criterio</h3>
                        <p class="dl-p3">Practiquen la diferencia entre querer, necesitar, gastar y esperar.</p>
                        <span class="dl-card-tag">Elecciones + confianza</span>
                    </article>

                    <article class="dl-benefit-card dl-benefit-card--green dl-reveal">
                        <span class="dl-icon-box" aria-hidden="true">
                            <svg viewBox="0 0 24 24">
                                <path d="M5 19h14M7 16l3-4 3 2 4-6"></path>
                                <path d="M15 8h2v2"></path>
                            </svg>
                        </span>
                        <h3 class="dl-h5">Crecer en equipo</h3>
                        <p class="dl-p3">Celebren avances, conversen sobre dinero y construyan hábitos sin presión.</p>
                        <span class="dl-card-tag">Familia + progreso</span>
                    </article>
                </div>
            </div>
        </section>

        <section class="dl-section dl-how" id="como-funciona" aria-labelledby="how-title">
            <div class="dl-container dl-how__grid">
                <div class="dl-how__copy dl-reveal" id="how-intro">
                    <span class="dl-kicker">Así se vive DOMUS</span>
                    <h2 class="dl-h2" id="how-title">De una conversación a un hábito.</h2>
                    <p class="dl-p2">Explora cada paso y descubre cómo una meta familiar se convierte en aprendizaje.</p>

                    <div class="dl-steps" aria-label="Pasos de la experiencia">
                        <button
                            class="dl-step is-active"
                            type="button"
                            aria-pressed="true"
                            data-dl-step
                            data-title="Elijan una meta que emocione"
                            data-copy="Una bici, un libro o una salida: cuando la meta tiene sentido, ahorrar deja de sentirse como castigo."
                            data-label="Meta creada"
                            data-progress="28"
                        >
                            <span class="dl-step__number">01</span>
                            <span>
                                <strong>Elijan una meta</strong>
                                <small>Algo que quieran lograr juntos.</small>
                            </span>
                            <span class="dl-step__arrow" aria-hidden="true">→</span>
                        </button>
                        <button
                            class="dl-step"
                            type="button"
                            aria-pressed="false"
                            data-dl-step
                            data-title="Practiquen con decisiones reales"
                            data-copy="Pequeñas misiones y conversaciones ayudan a comparar opciones, esperar y avanzar con intención."
                            data-label="Hábito en progreso"
                            data-progress="64"
                        >
                            <span class="dl-step__number">02</span>
                            <span>
                                <strong>Practiquen jugando</strong>
                                <small>Decisiones pequeñas, aprendizajes grandes.</small>
                            </span>
                            <span class="dl-step__arrow" aria-hidden="true">→</span>
                        </button>
                        <button
                            class="dl-step"
                            type="button"
                            aria-pressed="false"
                            data-dl-step
                            data-title="Celebren el progreso, no la perfección"
                            data-copy="Cada avance se vuelve visible para reforzar la constancia y abrir nuevas conversaciones en casa."
                            data-label="¡Logro en familia!"
                            data-progress="100"
                        >
                            <span class="dl-step__number">03</span>
                            <span>
                                <strong>Celebren el avance</strong>
                                <small>Reconocer el esfuerzo también enseña.</small>
                            </span>
                            <span class="dl-step__arrow" aria-hidden="true">→</span>
                        </button>
                    </div>
                </div>

                <div class="dl-demo dl-reveal" aria-live="polite">
                    <div class="dl-demo__topbar">
                        <span>
                            <span class="dl-demo__avatar" aria-hidden="true">D</span>
                            <span>
                                <small>Hola, familia 👋</small>
                                <strong>Su camino de hoy</strong>
                            </span>
                        </span>
                        <span class="dl-demo__points">+120 ✦</span>
                    </div>
                    <div class="dl-demo__body">
                        <span class="dl-demo__label" data-dl-demo-label>Meta creada</span>
                        <h3 class="dl-h4" data-dl-demo-title>Elijan una meta que emocione</h3>
                        <p class="dl-p3" data-dl-demo-copy>Una bici, un libro o una salida: cuando la meta tiene sentido, ahorrar deja de sentirse como castigo.</p>
                        <div class="dl-demo__goal">
                            <div class="dl-demo__goal-heading">
                                <span>
                                    <small>Meta</small>
                                    <strong>Mi primera bici</strong>
                                </span>
                                <span data-dl-demo-percent>28%</span>
                            </div>
                            <div class="dl-progress" aria-hidden="true">
                                <span data-dl-demo-bar style="width: 28%"></span>
                            </div>
                            <div class="dl-demo__amount">
                                <span>$840 ahorrados</span>
                                <span>$3,000</span>
                            </div>
                        </div>
                        <div class="dl-domi-note">
                            <span aria-hidden="true">★</span>
                            <p><strong>Tip de Domi:</strong> empiecen con una meta que puedan ver y nombrar.</p>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section class="dl-section dl-domi" id="domi" aria-labelledby="domi-title">
            <div class="dl-domi__sparkles" aria-hidden="true">✦　·　✦</div>
            <div class="dl-container dl-domi__grid">
                <div class="dl-domi__portrait dl-reveal">
                    <div class="dl-domi__circle">
                        <img
                            src="{{ asset('img/landing/domi-family-guide.webp') }}"
                            alt=""
                            width="1200"
                            height="1200"
                        >
                    </div>
                    <span class="dl-domi__badge">Tu guía en cada paso</span>
                </div>
                <div class="dl-domi__copy dl-reveal" id="domi-intro">
                    <span class="dl-kicker dl-kicker--light">Conoce a Domi</span>
                    <h2 class="dl-h2" id="domi-title">Un guía que hace las finanzas menos complicadas.</h2>
                    <p class="dl-p2">Domi explica con palabras simples, hace preguntas que despiertan curiosidad y celebra el esfuerzo de toda la familia.</p>
                    <div class="dl-domi__traits" aria-label="Personalidad de Domi">
                        <span><i class="dl-dot dl-dot--gold"></i> Amigable</span>
                        <span><i class="dl-dot dl-dot--green"></i> Claro</span>
                        <span><i class="dl-dot dl-dot--purple"></i> Motivador</span>
                        <span><i class="dl-dot dl-dot--pink"></i> Confiable</span>
                    </div>
                    <blockquote>
                        “Los grandes logros empiezan con una decisión pequeña.”
                    </blockquote>
                </div>
            </div>
        </section>

        <section class="dl-section dl-signup" id="unete" aria-labelledby="signup-title">
            <div class="dl-container">
                <div class="dl-signup__card dl-reveal">
                    <span class="dl-signup__star dl-signup__star--one" aria-hidden="true">✦</span>
                    <span class="dl-signup__star dl-signup__star--two" aria-hidden="true">✦</span>
                    <div class="dl-signup__copy">
                        <span class="dl-kicker">Esto apenas comienza</span>
                        <h2 class="dl-h2" id="signup-title">Creemos juntos una nueva forma de hablar de dinero en casa.</h2>
                        <p class="dl-p2">Déjanos tu correo y sé de las primeras familias en conocer DOMUS.</p>
                    </div>

                    <div class="dl-signup__form-wrap">
                        @if (session('landing_success'))
                            <div class="dl-alert dl-alert--success" role="status">
                                <span aria-hidden="true">✓</span>
                                <p>
                                    <strong>¡Ya eres parte de este comienzo!</strong>
                                    {{ session('landing_success') }}
                                </p>
                            </div>
                        @else
                            <form class="dl-email-form" method="POST" action="{{ route('landing.email.store') }}" data-dl-email-form>
                                @csrf
                                <label for="landing-email">Tu correo electrónico</label>
                                <div class="dl-email-form__row">
                                    <span class="dl-email-form__icon" aria-hidden="true">
                                        <svg viewBox="0 0 24 24">
                                            <rect x="3" y="5" width="18" height="14" rx="3"></rect>
                                            <path d="m5 8 7 5 7-5"></path>
                                        </svg>
                                    </span>
                                    <input
                                        id="landing-email"
                                        name="email"
                                        type="email"
                                        value="{{ old('email') }}"
                                        maxlength="254"
                                        autocomplete="email"
                                        inputmode="email"
                                        placeholder="nombre@correo.com"
                                        required
                                        aria-describedby="landing-email-note @error('email') landing-email-error @enderror"
                                        @error('email') aria-invalid="true" @enderror
                                    >
                                    <button class="dl-button dl-button--navy" type="submit" data-dl-submit>
                                        <span data-dl-submit-text>Quiero mi lugar</span>
                                        <span aria-hidden="true">→</span>
                                    </button>
                                </div>
                                @error('email')
                                    <p class="dl-field-error" id="landing-email-error">{{ $message }}</p>
                                @enderror
                                <p class="dl-form-note" id="landing-email-note">
                                    Sin spam. Solo novedades importantes de DOMUS.
                                </p>
                            </form>
                        @endif
                    </div>
                </div>
            </div>
        </section>
    </main>

    <footer class="dl-footer">
        <div class="dl-container dl-footer__inner">
            <div>
                <strong>DOMUS</strong>
                <span>Tu hogar. Tu banco. Tu futuro.</span>
            </div>
            <p>Una experiencia de educación financiera familiar en desarrollo.</p>
            <p>© {{ date('Y') }} DOMUS</p>
        </div>
    </footer>
</body>
</html>
