/**
 * The `.lp` marketing design system, shared by the landing page and every
 * product marketing subpage (MSITE). Scoped under `.lp` so nothing leaks into
 * the authenticated app.
 */
export const styles = `
.lp {
    --lp-bg: #eff5f5;
    --lp-surface: #ffffff;
    --lp-surface2: #f7fbfb;
    --lp-ink: #0b1a23;
    --lp-ink-soft: #33505c;
    --lp-muted: #6b8792;
    --lp-line: #dbe8e8;
    --lp-teal: #0bb39e;
    --lp-teal-bright: #12ddc2;
    --lp-teal-deep: #088a7b;
    --lp-coral: #ff6b54;
    --lp-coral-deep: #e94f38;
    --lp-amber: #ffc24b;
    --lp-band: #0b1a23;
    --lp-band-ink: #eafffb;
    --lp-shadow: 20px 40px 80px -40px rgba(11, 26, 35, 0.35);
    --lp-shadow-sm: 0 8px 24px -12px rgba(11, 26, 35, 0.25);
    --lp-r: 18px;
    --lp-r-lg: 28px;
    background: var(--lp-bg);
    color: var(--lp-ink);
    font-family: 'Plus Jakarta Sans', ui-sans-serif, system-ui, sans-serif;
    font-size: 17px;
    line-height: 1.6;
    -webkit-font-smoothing: antialiased;
    min-height: 100vh;
    overflow-x: hidden;
}
.dark .lp {
    --lp-bg: #071319;
    --lp-surface: #0f2029;
    --lp-surface2: #132832;
    --lp-ink: #eafaf7;
    --lp-ink-soft: #b3ccce;
    --lp-muted: #7a9aa0;
    --lp-line: #1d353e;
    --lp-band: #050f14;
    --lp-band-ink: #eafffb;
    --lp-shadow: 20px 40px 80px -40px rgba(0, 0, 0, 0.7);
    --lp-shadow-sm: 0 8px 24px -12px rgba(0, 0, 0, 0.6);
}
html:has(.lp) {
    scroll-behavior: smooth;
}
.lp *,
.lp *::before,
.lp *::after {
    box-sizing: border-box;
}
.lp h1,
.lp h2,
.lp h3 {
    font-family: 'Bricolage Grotesque', 'Plus Jakarta Sans', sans-serif;
    font-weight: 700;
    line-height: 1.02;
    letter-spacing: -0.02em;
    text-wrap: balance;
    margin: 0;
}
.lp p {
    margin: 0;
}
.lp a {
    color: inherit;
    text-decoration: none;
}
.lp .wrap {
    width: min(1180px, 100% - 40px);
    margin-inline: auto;
}
.lp .eyebrow {
    font-family: 'Space Mono', monospace;
    font-size: 12.5px;
    letter-spacing: 0.18em;
    text-transform: uppercase;
    color: var(--lp-teal-deep);
    font-weight: 700;
}
.lp .mono {
    font-family: 'Space Mono', monospace;
}
.lp .btn {
    display: inline-flex;
    align-items: center;
    gap: 0.5em;
    font-weight: 700;
    font-size: 16px;
    padding: 14px 24px;
    border-radius: 999px;
    border: 2px solid transparent;
    cursor: pointer;
    transition: transform 0.18s ease, box-shadow 0.18s ease, background 0.18s ease, border-color 0.18s ease, color 0.18s ease;
}
.lp .btn svg {
    width: 18px;
    height: 18px;
}
.lp .btn-primary {
    background: var(--lp-coral);
    color: #fff;
    box-shadow: 0 12px 28px -10px var(--lp-coral);
}
.lp .btn-primary:hover {
    background: var(--lp-coral-deep);
    transform: translateY(-2px);
    box-shadow: 0 18px 34px -10px var(--lp-coral);
}
.lp .btn-ghost {
    border-color: var(--lp-line);
    color: var(--lp-ink);
    background: var(--lp-surface);
}
.lp .btn-ghost:hover {
    border-color: var(--lp-teal);
    color: var(--lp-teal-deep);
    transform: translateY(-2px);
}
.lp .nav {
    position: sticky;
    top: 0;
    z-index: 50;
    background: color-mix(in srgb, var(--lp-bg) 82%, transparent);
    backdrop-filter: blur(12px);
    border-bottom: 1px solid transparent;
    transition: border-color 0.3s;
}
.lp .nav.scrolled {
    border-color: var(--lp-line);
}
.lp .nav-inner {
    display: flex;
    align-items: center;
    gap: 24px;
    height: 72px;
}
.lp .brand {
    display: flex;
    align-items: center;
    gap: 10px;
    font-family: 'Bricolage Grotesque', sans-serif;
    font-weight: 800;
    font-size: 21px;
    letter-spacing: -0.02em;
    color: var(--lp-ink);
}
.lp .brand .mark {
    width: 34px;
    height: 34px;
    border-radius: 10px;
    background: linear-gradient(140deg, var(--lp-teal-bright), var(--lp-teal-deep));
    display: grid;
    place-items: center;
    box-shadow: 0 6px 16px -6px var(--lp-teal);
    flex-shrink: 0;
}
.lp .brand .mark svg {
    width: 20px;
    height: 20px;
}
.lp .nav-links {
    display: flex;
    gap: 28px;
    margin-left: 12px;
    font-weight: 600;
    font-size: 15px;
    color: var(--lp-ink-soft);
}
.lp .nav-links a:hover {
    color: var(--lp-teal-deep);
}
.lp .nav-links a[aria-current='page'] {
    color: var(--lp-teal-deep);
}
.lp .nav-cta {
    margin-left: auto;
    display: flex;
    align-items: center;
    gap: 14px;
}
.lp .theme-toggle {
    width: 40px;
    height: 40px;
    border-radius: 12px;
    border: 1px solid var(--lp-line);
    background: var(--lp-surface);
    color: var(--lp-ink);
    cursor: pointer;
    display: grid;
    place-items: center;
    flex-shrink: 0;
}
.lp .theme-toggle:hover {
    border-color: var(--lp-teal);
    color: var(--lp-teal-deep);
}
.lp .theme-toggle svg {
    width: 18px;
    height: 18px;
}
.lp .login-link {
    font-weight: 700;
    font-size: 15px;
    color: var(--lp-ink);
}
.lp .login-link:hover {
    color: var(--lp-teal-deep);
}
.lp .hero {
    position: relative;
    padding: 64px 0 40px;
}
.lp .hero-grid {
    display: grid;
    grid-template-columns: 1.02fr 1fr;
    gap: 40px;
    align-items: center;
}
.lp .badge-row {
    display: inline-flex;
    align-items: center;
    gap: 10px;
    padding: 7px 7px 7px 14px;
    border-radius: 999px;
    background: var(--lp-surface);
    border: 1px solid var(--lp-line);
    box-shadow: var(--lp-shadow-sm);
    font-size: 13.5px;
    font-weight: 600;
    color: var(--lp-ink-soft);
    margin-bottom: 26px;
}
.lp .badge-row .pill {
    background: color-mix(in srgb, var(--lp-teal) 16%, transparent);
    color: var(--lp-teal-deep);
    font-family: 'Space Mono', monospace;
    font-weight: 700;
    font-size: 12px;
    padding: 4px 10px;
    border-radius: 999px;
}
.lp .hero h1 {
    font-size: clamp(40px, 5.6vw, 68px);
    font-weight: 800;
}
.lp .hero h1 .hl {
    position: relative;
    color: var(--lp-teal-deep);
    white-space: nowrap;
}
.lp .hero h1 .hl svg {
    position: absolute;
    left: 0;
    bottom: -0.18em;
    width: 100%;
    height: 0.3em;
    overflow: visible;
}
.lp .hero h1 .hl path {
    stroke: var(--lp-amber);
    stroke-width: 8;
    fill: none;
    stroke-linecap: round;
    stroke-dasharray: 340;
    stroke-dashoffset: 340;
}
.lp .lead {
    margin-top: 22px;
    font-size: 19px;
    color: var(--lp-ink-soft);
    max-width: 34ch;
}
.lp .hero-cta {
    margin-top: 32px;
    display: flex;
    gap: 14px;
    flex-wrap: wrap;
}
.lp .hero-note {
    margin-top: 18px;
    font-size: 13.5px;
    color: var(--lp-muted);
    display: flex;
    align-items: center;
    gap: 8px;
}
.lp .hero-note svg {
    width: 15px;
    height: 15px;
    color: var(--lp-teal);
}
.lp .scene {
    position: relative;
    aspect-ratio: 1 / 0.92;
}
.lp .scene .blob {
    position: absolute;
    inset: -6% -10% -14% -6%;
    background: radial-gradient(60% 60% at 65% 35%, color-mix(in srgb, var(--lp-teal) 26%, transparent), transparent 70%);
    filter: blur(6px);
    z-index: 0;
}
.lp .panel {
    position: absolute;
    inset: 6% 4% 8% 6%;
    background: var(--lp-surface);
    border: 1px solid var(--lp-line);
    border-radius: var(--lp-r-lg);
    box-shadow: var(--lp-shadow);
    overflow: hidden;
    z-index: 1;
}
.lp .panel-top {
    display: flex;
    align-items: center;
    gap: 7px;
    padding: 14px 18px;
    border-bottom: 1px solid var(--lp-line);
}
.lp .panel-top i {
    width: 10px;
    height: 10px;
    border-radius: 50%;
    background: var(--lp-line);
    display: block;
}
.lp .panel-top i:nth-child(1) {
    background: var(--lp-coral);
}
.lp .panel-top i:nth-child(2) {
    background: var(--lp-amber);
}
.lp .panel-top i:nth-child(3) {
    background: var(--lp-teal);
}
.lp .panel-top span {
    margin-left: 8px;
    font-family: 'Space Mono', monospace;
    font-size: 11.5px;
    color: var(--lp-muted);
}
.lp .panel-body {
    padding: 20px 20px 24px;
}
.lp .kpi-row {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 10px;
    margin-bottom: 18px;
}
.lp .kpi {
    background: var(--lp-surface2);
    border: 1px solid var(--lp-line);
    border-radius: 14px;
    padding: 12px 12px 14px;
}
.lp .kpi .l {
    font-size: 10.5px;
    font-weight: 700;
    color: var(--lp-muted);
    text-transform: uppercase;
    letter-spacing: 0.06em;
}
.lp .kpi .v {
    font-family: 'Bricolage Grotesque', sans-serif;
    font-weight: 800;
    font-size: 25px;
    margin-top: 4px;
    font-variant-numeric: tabular-nums;
}
.lp .kpi .v.teal {
    color: var(--lp-teal-deep);
}
.lp .kpi .v.coral {
    color: var(--lp-coral);
}
.lp .chart {
    height: 128px;
    position: relative;
}
.lp .chart svg {
    width: 100%;
    height: 100%;
    overflow: visible;
}
.lp .chart .grid line {
    stroke: var(--lp-line);
    stroke-width: 1;
}
.lp .chart .area {
    fill: url(#lpAreaFill);
    opacity: 0;
}
.lp .chart .line {
    fill: none;
    stroke: var(--lp-teal);
    stroke-width: 3.5;
    stroke-linecap: round;
    stroke-linejoin: round;
    stroke-dasharray: 520;
    stroke-dashoffset: 520;
}
.lp .chart .dot {
    fill: var(--lp-surface);
    stroke: var(--lp-teal);
    stroke-width: 3.5;
    opacity: 0;
}
.lp .chart .bars rect {
    fill: color-mix(in srgb, var(--lp-teal) 22%, transparent);
    transform-origin: bottom;
    transform: scaleY(0);
}
.lp .float {
    position: absolute;
    z-index: 3;
    background: var(--lp-surface);
    border: 1px solid var(--lp-line);
    border-radius: 16px;
    box-shadow: var(--lp-shadow);
    padding: 12px 15px;
    display: flex;
    align-items: center;
    gap: 11px;
    font-weight: 700;
    font-size: 14px;
    color: var(--lp-ink);
    opacity: 0;
}
.lp .float .ic {
    width: 34px;
    height: 34px;
    border-radius: 10px;
    display: grid;
    place-items: center;
    flex-shrink: 0;
}
.lp .float .ic svg {
    width: 18px;
    height: 18px;
}
.lp .float small {
    display: block;
    font-weight: 600;
    font-size: 11.5px;
    color: var(--lp-muted);
}
.lp .float.f1 {
    top: 2%;
    right: -4%;
}
.lp .float.f1 .ic {
    background: color-mix(in srgb, var(--lp-coral) 18%, transparent);
    color: var(--lp-coral-deep);
}
.lp .float.f2 {
    bottom: 20%;
    left: -8%;
}
.lp .float.f2 .ic {
    background: color-mix(in srgb, var(--lp-teal) 18%, transparent);
    color: var(--lp-teal-deep);
}
.lp .float.f3 {
    bottom: -2%;
    right: 6%;
}
.lp .float.f3 .ic {
    background: color-mix(in srgb, var(--lp-amber) 26%, transparent);
    color: #a5720a;
}
.lp .spark {
    position: absolute;
    z-index: 4;
    top: 12%;
    left: -3%;
    width: 60px;
    height: 60px;
    opacity: 0;
}
.lp .spark svg {
    width: 100%;
    height: 100%;
    filter: drop-shadow(0 8px 16px rgba(255, 107, 84, 0.4));
}
.lp .reveal {
    opacity: 0;
    transform: translateY(26px);
    transition: opacity 0.7s cubic-bezier(0.2, 0.7, 0.2, 1), transform 0.7s cubic-bezier(0.2, 0.7, 0.2, 1);
}
.lp .reveal.in {
    opacity: 1;
    transform: none;
}
.lp .trust {
    padding: 30px 0 8px;
}
.lp .trust-inner {
    display: flex;
    align-items: center;
    gap: 14px 34px;
    flex-wrap: wrap;
    justify-content: center;
    color: var(--lp-muted);
    font-weight: 700;
    font-size: 14px;
}
.lp .trust-inner .dot {
    width: 5px;
    height: 5px;
    border-radius: 50%;
    background: var(--lp-teal);
}
.lp .stats {
    margin: 56px 0;
}
.lp .stats-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 16px;
    background: var(--lp-band);
    border-radius: var(--lp-r-lg);
    padding: 40px 32px;
    color: var(--lp-band-ink);
    position: relative;
    overflow: hidden;
}
.lp .stats-grid::before {
    content: '';
    position: absolute;
    inset: 0;
    background: radial-gradient(40% 60% at 10% 0%, color-mix(in srgb, var(--lp-teal) 34%, transparent), transparent 60%),
        radial-gradient(40% 70% at 100% 100%, color-mix(in srgb, var(--lp-coral) 28%, transparent), transparent 60%);
    opacity: 0.6;
}
.lp .stat {
    position: relative;
    text-align: center;
}
.lp .stat .num {
    font-family: 'Bricolage Grotesque', sans-serif;
    font-weight: 800;
    font-size: clamp(34px, 4vw, 50px);
    letter-spacing: -0.03em;
    font-variant-numeric: tabular-nums;
    background: linear-gradient(120deg, var(--lp-teal-bright), var(--lp-amber));
    -webkit-background-clip: text;
    background-clip: text;
    color: transparent;
}
.lp .stat .cap {
    margin-top: 4px;
    font-size: 14px;
    font-weight: 600;
    color: color-mix(in srgb, var(--lp-band-ink) 72%, transparent);
}
.lp .sec {
    padding: 64px 0;
}
.lp .sec-head {
    max-width: 620px;
    margin-bottom: 44px;
}
.lp .sec-head.center {
    margin-inline: auto;
    text-align: center;
}
.lp .sec-head h2 {
    font-size: clamp(30px, 4vw, 46px);
    margin-top: 14px;
}
.lp .sec-head p {
    margin-top: 16px;
    font-size: 18px;
    color: var(--lp-ink-soft);
}
.lp .features {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 18px;
}
.lp .card {
    background: var(--lp-surface);
    border: 1px solid var(--lp-line);
    border-radius: var(--lp-r);
    padding: 26px;
    transition: transform 0.22s ease, box-shadow 0.22s ease, border-color 0.22s ease;
    position: relative;
    overflow: hidden;
}
.lp .card:hover {
    transform: translateY(-6px);
    box-shadow: var(--lp-shadow);
    border-color: color-mix(in srgb, var(--lp-teal) 40%, var(--lp-line));
}
.lp .card .ico {
    width: 52px;
    height: 52px;
    border-radius: 15px;
    display: grid;
    place-items: center;
    margin-bottom: 18px;
    color: #fff;
    transition: transform 0.22s ease;
}
.lp .card:hover .ico {
    transform: rotate(-6deg) scale(1.06);
}
.lp .card .ico svg {
    width: 26px;
    height: 26px;
}
.lp .card h3 {
    font-size: 20px;
    font-weight: 700;
}
.lp .card p {
    margin-top: 9px;
    color: var(--lp-ink-soft);
    font-size: 15.5px;
}
.lp .card .tag {
    margin-top: 16px;
    display: inline-block;
    font-family: 'Space Mono', monospace;
    font-size: 11.5px;
    font-weight: 700;
    color: var(--lp-teal-deep);
    background: color-mix(in srgb, var(--lp-teal) 12%, transparent);
    padding: 4px 10px;
    border-radius: 999px;
}
.lp .ico.c-teal {
    background: linear-gradient(140deg, var(--lp-teal-bright), var(--lp-teal-deep));
}
.lp .ico.c-coral {
    background: linear-gradient(140deg, #ff9075, var(--lp-coral-deep));
}
.lp .ico.c-amber {
    background: linear-gradient(140deg, var(--lp-amber), #f39a12);
}
.lp .ico.c-navy {
    background: linear-gradient(140deg, #2b4a58, var(--lp-band));
}
.lp .ico.c-cyan {
    background: linear-gradient(140deg, #4fd1c5, #0891b2);
}
.lp .ico.c-pink {
    background: linear-gradient(140deg, #ff8a8a, #ff5470);
}
.lp .flow {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 18px;
    counter-reset: step;
}
.lp .step {
    position: relative;
    background: var(--lp-surface2);
    border: 1px solid var(--lp-line);
    border-radius: var(--lp-r);
    padding: 28px 24px;
}
.lp .step .n {
    counter-increment: step;
    font-family: 'Space Mono', monospace;
    font-weight: 700;
    font-size: 13px;
    color: var(--lp-teal-deep);
}
.lp .step .n::before {
    content: '0' counter(step);
}
.lp .step h3 {
    font-size: 20px;
    margin-top: 10px;
}
.lp .step p {
    margin-top: 8px;
    color: var(--lp-ink-soft);
    font-size: 15px;
}
.lp .step .arrow {
    position: absolute;
    top: 50%;
    right: -22px;
    transform: translateY(-50%);
    color: var(--lp-teal);
    z-index: 2;
}
.lp .step .arrow svg {
    width: 26px;
    height: 26px;
}
.lp .cta-band {
    margin: 40px 0 64px;
    background: var(--lp-band);
    border-radius: var(--lp-r-lg);
    padding: 60px 40px;
    text-align: center;
    color: var(--lp-band-ink);
    position: relative;
    overflow: hidden;
}
.lp .cta-band::before {
    content: '';
    position: absolute;
    inset: 0;
    background: radial-gradient(50% 80% at 20% 0%, color-mix(in srgb, var(--lp-teal) 40%, transparent), transparent 55%),
        radial-gradient(45% 70% at 90% 100%, color-mix(in srgb, var(--lp-coral) 34%, transparent), transparent 55%);
    opacity: 0.7;
}
.lp .cta-band > * {
    position: relative;
}
.lp .cta-band h2 {
    font-size: clamp(30px, 4.4vw, 48px);
}
.lp .cta-band p {
    margin: 16px auto 30px;
    max-width: 46ch;
    color: color-mix(in srgb, var(--lp-band-ink) 78%, transparent);
    font-size: 18px;
}
.lp .cta-band .btn-ghost {
    background: transparent;
    color: var(--lp-band-ink);
    border-color: color-mix(in srgb, var(--lp-band-ink) 40%, transparent);
}
.lp .cta-band .btn-ghost:hover {
    border-color: var(--lp-teal-bright);
    color: var(--lp-teal-bright);
}
.lp .foot {
    border-top: 1px solid var(--lp-line);
    padding: 56px 0 36px;
    color: var(--lp-muted);
    font-size: 14px;
}
.lp .foot-grid {
    display: grid;
    grid-template-columns: minmax(220px, 1.3fr) 1fr 1fr minmax(240px, 1.4fr);
    gap: 40px;
}
@media (max-width: 900px) {
    .lp .foot-grid {
        grid-template-columns: 1fr 1fr;
    }
}
@media (max-width: 560px) {
    .lp .foot-grid {
        grid-template-columns: 1fr;
    }
}
.lp .foot-brand p {
    margin-top: 14px;
    max-width: 320px;
    line-height: 1.65;
}
.lp .foot-col {
    display: flex;
    flex-direction: column;
    gap: 10px;
    align-items: flex-start;
}
.lp .foot-col h4 {
    font-size: 12.5px;
    font-weight: 800;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    color: var(--lp-ink-soft);
    margin-bottom: 4px;
}
.lp .foot-col a {
    color: var(--lp-muted);
    font-weight: 600;
}
.lp .foot-col a:hover {
    color: var(--lp-teal-deep);
}
.lp .foot-news p {
    line-height: 1.6;
    margin-bottom: 4px;
}
.lp .news-row {
    display: flex;
    gap: 8px;
    width: 100%;
}
.lp .news-row input {
    flex: 1;
    min-width: 0;
    border: 1px solid var(--lp-line);
    border-radius: 10px;
    padding: 10px 14px;
    font: inherit;
    font-size: 14px;
    color: var(--lp-ink);
    background: #fff;
}
.lp .news-row input:focus {
    outline: 2px solid var(--lp-teal);
    outline-offset: 1px;
    border-color: var(--lp-teal);
}
.lp .news-row button {
    border: 0;
    border-radius: 10px;
    padding: 10px 18px;
    font: inherit;
    font-size: 14px;
    font-weight: 700;
    color: #fff;
    background: linear-gradient(140deg, var(--lp-teal-bright), var(--lp-teal-deep));
    cursor: pointer;
    white-space: nowrap;
}
.lp .news-row button:hover {
    filter: brightness(1.06);
}
.lp .news-row button:disabled {
    opacity: 0.6;
    cursor: default;
}
.lp .news-done {
    font-weight: 700;
    color: var(--lp-teal-deep);
}
.lp .news-error {
    margin-top: 6px;
    font-size: 13px;
    color: var(--lp-coral);
}
.lp .foot-bottom {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 16px;
    flex-wrap: wrap;
    margin-top: 44px;
    padding-top: 20px;
    border-top: 1px solid var(--lp-line);
    font-size: 13px;
}
.lp .foot-links {
    display: flex;
    align-items: center;
    gap: 20px;
    flex-wrap: wrap;
}
.lp .foot-links a {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    font-weight: 700;
    color: var(--lp-teal-deep);
}
.lp .foot-links a:hover {
    color: var(--lp-coral);
}
.lp .foot-links a svg {
    width: 15px;
    height: 15px;
}
@keyframes lp-float-y {
    0%,
    100% {
        transform: translateY(0);
    }
    50% {
        transform: translateY(-12px);
    }
}
@keyframes lp-orbit {
    0% {
        transform: rotate(0deg) translateX(140px) rotate(0deg);
    }
    100% {
        transform: rotate(360deg) translateX(140px) rotate(-360deg);
    }
}
@keyframes lp-pop-in {
    from {
        opacity: 0;
        transform: translateY(14px) scale(0.9);
    }
    to {
        opacity: 1;
        transform: none;
    }
}
@keyframes lp-draw {
    to {
        stroke-dashoffset: 0;
    }
}
@keyframes lp-fade-in {
    to {
        opacity: 1;
    }
}
@keyframes lp-grow-bar {
    to {
        transform: scaleY(1);
    }
}
/* The hero load sequence plays on mount, driven purely by CSS so it never
   depends on JS timing. Each element starts in its hidden base state (set
   above) and animates to its resting state; the floats then drift on a gentle
   loop that begins once the pop-in has finished. */
.lp .chart .line {
    animation: lp-draw 1.6s 0.5s cubic-bezier(0.4, 0, 0.2, 1) forwards;
}
.lp .chart .area {
    animation: lp-fade-in 1s 1.6s ease forwards;
}
.lp .chart .dot {
    animation: lp-fade-in 0.5s 1.9s ease forwards;
}
.lp .chart .bars rect {
    animation: lp-grow-bar 0.8s cubic-bezier(0.2, 0.9, 0.3, 1) forwards;
}
.lp .chart .bars rect:nth-child(1) {
    animation-delay: 0.6s;
}
.lp .chart .bars rect:nth-child(2) {
    animation-delay: 0.75s;
}
.lp .chart .bars rect:nth-child(3) {
    animation-delay: 0.9s;
}
.lp .chart .bars rect:nth-child(4) {
    animation-delay: 1.05s;
}
.lp .chart .bars rect:nth-child(5) {
    animation-delay: 1.2s;
}
.lp .hero h1 .hl path {
    animation: lp-draw 0.9s 0.9s ease forwards;
}
.lp .float {
    animation: lp-pop-in 0.6s ease forwards, lp-float-y 5s ease-in-out infinite;
}
.lp .float.f1 {
    animation-delay: 1.3s, 2.6s;
}
.lp .float.f2 {
    animation-delay: 1.55s, 2.95s;
}
.lp .float.f3 {
    animation-delay: 1.8s, 3.2s;
}
.lp .spark {
    animation: lp-fade-in 0.5s 1.3s ease forwards, lp-orbit 14s linear infinite;
}
@media (max-width: 900px) {
    .lp .hero-grid {
        grid-template-columns: 1fr;
        gap: 30px;
    }
    .lp .scene {
        max-width: 460px;
        margin-inline: auto;
        width: 100%;
    }
    .lp .features,
    .lp .flow,
    .lp .stats-grid {
        grid-template-columns: 1fr 1fr;
    }
    .lp .nav-links {
        display: none;
    }
    .lp .step .arrow {
        display: none;
    }
}
@media (max-width: 560px) {
    .lp .features,
    .lp .flow,
    .lp .stats-grid {
        grid-template-columns: 1fr;
    }
    .lp .hero {
        padding-top: 36px;
    }
    .lp .float.f1,
    .lp .float.f2 {
        display: none;
    }
}
@media (prefers-reduced-motion: reduce) {
    .lp *,
    .lp *::before,
    .lp *::after {
        animation: none !important;
        transition: none !important;
    }
    .lp .reveal {
        opacity: 1;
        transform: none;
    }
    .lp .chart .line,
    .lp .hero h1 .hl path {
        stroke-dashoffset: 0;
    }
    .lp .chart .area,
    .lp .chart .dot,
    .lp .float {
        opacity: 1;
    }
    .lp .chart .bars rect {
        transform: scaleY(1);
    }
}

/* ---- Marketing subpages (MSITE) ---- */
.lp .sub-hero {
    position: relative;
    padding: 88px 0 56px;
    text-align: center;
    overflow: hidden;
}
.lp .sub-hero::before {
    content: '';
    position: absolute;
    inset: 0;
    background:
        radial-gradient(42% 60% at 15% 0%, color-mix(in srgb, var(--lp-teal) 18%, transparent), transparent 62%),
        radial-gradient(38% 52% at 88% 6%, color-mix(in srgb, var(--lp-coral) 13%, transparent), transparent 60%);
    pointer-events: none;
}
.lp .sub-hero::after {
    content: '';
    position: absolute;
    inset: 0;
    background-image: radial-gradient(color-mix(in srgb, var(--lp-ink) 8%, transparent) 1px, transparent 1px);
    background-size: 22px 22px;
    -webkit-mask-image: radial-gradient(65% 75% at 50% 0%, black, transparent 78%);
    mask-image: radial-gradient(65% 75% at 50% 0%, black, transparent 78%);
    pointer-events: none;
}
.lp .sub-hero .wrap {
    position: relative;
    max-width: 880px;
}
.lp .sub-hero .eyebrow-chip {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    font-family: 'Space Mono', monospace;
    font-size: 12.5px;
    letter-spacing: 0.16em;
    text-transform: uppercase;
    font-weight: 700;
    color: var(--lp-teal-deep);
    background: var(--lp-surface);
    border: 1px solid color-mix(in srgb, var(--lp-teal) 35%, var(--lp-line));
    border-radius: 999px;
    padding: 8px 16px;
    box-shadow: var(--lp-shadow-sm);
}
.lp .sub-hero .eyebrow-chip::before {
    content: '';
    width: 7px;
    height: 7px;
    border-radius: 50%;
    background: linear-gradient(140deg, var(--lp-teal-bright), var(--lp-coral));
}
.lp .sub-hero h1 {
    font-size: clamp(36px, 5vw, 60px);
    font-weight: 800;
    margin-top: 22px;
    letter-spacing: -0.02em;
    text-wrap: balance;
}
.lp .sub-hero h1 .accent {
    color: var(--lp-teal-deep);
}
.lp .sub-hero .lead {
    margin: 20px auto 0;
    font-size: 19px;
    color: var(--lp-ink-soft);
    max-width: 56ch;
}
.lp .sub-chips {
    margin-top: 32px;
    display: flex;
    gap: 10px;
    justify-content: center;
    flex-wrap: wrap;
}
.lp .sub-chips span {
    font-family: 'Space Mono', monospace;
    font-size: 12px;
    font-weight: 700;
    letter-spacing: 0.04em;
    color: var(--lp-ink-soft);
    background: var(--lp-surface);
    border: 1px solid var(--lp-line);
    border-radius: 999px;
    padding: 7px 14px;
    box-shadow: var(--lp-shadow-sm);
}
.lp .sub-section {
    padding: 44px 0;
}
.lp .sub-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 18px;
}
.lp .sub-band {
    position: relative;
    overflow: hidden;
    background: var(--lp-band);
    color: var(--lp-band-ink);
    border-radius: var(--lp-r-lg);
    padding: 36px 40px;
    display: flex;
    gap: 22px 48px;
    flex-wrap: wrap;
    align-items: center;
    justify-content: space-between;
}
.lp .sub-band::before {
    content: '';
    position: absolute;
    inset: 0;
    background:
        radial-gradient(40% 70% at 8% 0%, color-mix(in srgb, var(--lp-teal) 34%, transparent), transparent 60%),
        radial-gradient(40% 70% at 95% 100%, color-mix(in srgb, var(--lp-coral) 26%, transparent), transparent 60%);
    opacity: 0.6;
}
.lp .sub-band > * {
    position: relative;
}
.lp .sub-band .b-num {
    font-family: 'Bricolage Grotesque', sans-serif;
    font-weight: 800;
    font-size: clamp(28px, 3.4vw, 40px);
    letter-spacing: -0.02em;
    background: linear-gradient(120deg, var(--lp-teal-bright), var(--lp-amber));
    -webkit-background-clip: text;
    background-clip: text;
    color: transparent;
}
.lp .sub-band .b-cap {
    margin-top: 2px;
    font-size: 13.5px;
    font-weight: 600;
    color: color-mix(in srgb, var(--lp-band-ink) 72%, transparent);
}
.lp .timeline {
    max-width: 780px;
    margin: 0 auto;
    display: grid;
    gap: 16px;
}
.lp .tstep {
    display: grid;
    grid-template-columns: 56px 1fr;
    gap: 20px;
    align-items: start;
    background: var(--lp-surface);
    border: 1px solid var(--lp-line);
    border-radius: var(--lp-r);
    padding: 26px;
    transition: transform 0.22s ease, box-shadow 0.22s ease, border-color 0.22s ease;
}
.lp .tstep:hover {
    transform: translateX(8px);
    box-shadow: var(--lp-shadow);
    border-color: color-mix(in srgb, var(--lp-teal) 40%, var(--lp-line));
}
.lp .tstep .n {
    width: 48px;
    height: 48px;
    border-radius: 15px;
    display: grid;
    place-items: center;
    font-family: 'Space Mono', monospace;
    font-weight: 700;
    font-size: 15px;
    color: #fff;
    background: linear-gradient(140deg, var(--lp-teal-bright), var(--lp-teal-deep));
    box-shadow: 0 8px 18px -8px var(--lp-teal);
}
.lp .tstep:nth-child(even) .n {
    background: linear-gradient(140deg, #ff9075, var(--lp-coral-deep));
    box-shadow: 0 8px 18px -8px var(--lp-coral);
}
.lp .tstep h3 {
    font-size: 19px;
}
.lp .tstep p {
    margin-top: 8px;
    font-size: 15.5px;
    color: var(--lp-ink-soft);
    max-width: 60ch;
}
.lp .faq-list {
    display: grid;
    gap: 14px;
    margin: 0 auto;
    max-width: 820px;
}
.lp .faq-list details {
    background: var(--lp-surface);
    border: 1px solid var(--lp-line);
    border-left: 3px solid transparent;
    border-radius: var(--lp-r);
    padding: 20px 24px;
    transition: border-color 0.2s ease, box-shadow 0.2s ease;
}
.lp .faq-list details[open] {
    border-left-color: var(--lp-teal);
    box-shadow: var(--lp-shadow-sm);
}
.lp .faq-list summary {
    cursor: pointer;
    font-weight: 700;
    font-size: 17px;
    list-style: none;
    display: flex;
    justify-content: space-between;
    gap: 16px;
    align-items: center;
}
.lp .faq-list summary::-webkit-details-marker {
    display: none;
}
.lp .faq-list summary::after {
    content: '+';
    display: grid;
    place-items: center;
    width: 28px;
    height: 28px;
    border-radius: 50%;
    background: color-mix(in srgb, var(--lp-teal) 14%, transparent);
    color: var(--lp-teal-deep);
    font-family: 'Space Mono', monospace;
    font-size: 17px;
    flex-shrink: 0;
    transition: transform 0.2s ease;
}
.lp .faq-list details[open] summary::after {
    content: '\\2013';
    transform: rotate(180deg);
}
.lp .faq-list details p {
    margin-top: 12px;
    font-size: 15.5px;
    color: var(--lp-ink-soft);
    line-height: 1.65;
    max-width: 65ch;
}
.lp .honesty {
    position: relative;
    overflow: hidden;
    max-width: 880px;
    margin: 0 auto;
    background: var(--lp-surface);
    border: 1px solid color-mix(in srgb, var(--lp-amber) 45%, var(--lp-line));
    border-radius: var(--lp-r-lg);
    padding: 42px 46px;
    box-shadow: var(--lp-shadow-sm);
}
.lp .honesty::before {
    content: '\\201C';
    position: absolute;
    top: -36px;
    right: 8px;
    font-family: 'Bricolage Grotesque', sans-serif;
    font-size: 230px;
    line-height: 1;
    color: color-mix(in srgb, var(--lp-amber) 22%, transparent);
    pointer-events: none;
}
.lp .honesty h2 {
    font-size: clamp(24px, 3vw, 32px);
}
.lp .honesty p {
    position: relative;
    margin-top: 14px;
    font-size: 16.5px;
    line-height: 1.7;
    color: var(--lp-ink-soft);
    max-width: 62ch;
}
.lp .prose p {
    font-size: 17px;
    line-height: 1.75;
    color: var(--lp-ink-soft);
    max-width: 68ch;
}
.lp .prose p + p {
    margin-top: 18px;
}
.lp .contact-grid {
    display: grid;
    grid-template-columns: 1.15fr 0.85fr;
    gap: 22px;
    align-items: start;
    max-width: 980px;
    margin: 0 auto;
}
.lp .contact-card {
    background: var(--lp-surface);
    border: 1px solid var(--lp-line);
    border-radius: var(--lp-r-lg);
    padding: 32px;
    box-shadow: var(--lp-shadow-sm);
}
.lp .contact-form {
    display: grid;
    gap: 16px;
}
.lp .contact-form label {
    display: grid;
    gap: 7px;
    font-weight: 700;
    font-size: 14px;
}
.lp .contact-form input,
.lp .contact-form textarea {
    border: 1px solid var(--lp-line);
    border-radius: 12px;
    padding: 12px 15px;
    font: inherit;
    font-size: 15px;
    color: var(--lp-ink);
    background: var(--lp-bg);
    transition: border-color 0.15s ease, box-shadow 0.15s ease;
}
.lp .contact-form input:focus,
.lp .contact-form textarea:focus {
    outline: none;
    border-color: var(--lp-teal);
    box-shadow: 0 0 0 3px color-mix(in srgb, var(--lp-teal) 22%, transparent);
}
.lp .contact-form textarea {
    min-height: 150px;
    resize: vertical;
}
.lp .aside-card {
    background: var(--lp-surface2);
    border: 1px solid var(--lp-line);
    border-radius: var(--lp-r-lg);
    padding: 28px;
    display: grid;
    gap: 20px;
}
.lp .aside-item {
    display: flex;
    gap: 14px;
    align-items: flex-start;
}
.lp .aside-item .mini-ico {
    width: 38px;
    height: 38px;
    border-radius: 12px;
    display: grid;
    place-items: center;
    color: #fff;
    background: linear-gradient(140deg, var(--lp-teal-bright), var(--lp-teal-deep));
    flex-shrink: 0;
}
.lp .aside-item:nth-child(even) .mini-ico {
    background: linear-gradient(140deg, #ff9075, var(--lp-coral-deep));
}
.lp .aside-item .mini-ico svg {
    width: 19px;
    height: 19px;
}
.lp .aside-item h4 {
    font-size: 15.5px;
}
.lp .aside-item p {
    margin-top: 4px;
    font-size: 14px;
    color: var(--lp-ink-soft);
}
.lp .aside-item a {
    color: var(--lp-teal-deep);
    font-weight: 700;
}
@media (max-width: 900px) {
    .lp .contact-grid {
        grid-template-columns: 1fr;
    }
}
@media (max-width: 720px) {
    .lp .sub-hero {
        padding: 64px 0 40px;
    }
    .lp .sub-band {
        padding: 28px;
    }
    .lp .tstep {
        grid-template-columns: 46px 1fr;
        gap: 14px;
        padding: 20px;
    }
    .lp .honesty {
        padding: 30px 26px;
    }
}

/* Hero banners on subpages: reuse the landing scene/panel/float system. */
.lp .sub-hero-grid {
    display: grid;
    grid-template-columns: 1.02fr 0.98fr;
    gap: 48px;
    align-items: center;
    text-align: left;
}
.lp .sub-hero-grid .lead {
    margin-left: 0;
}
.lp .sub-hero-grid .sub-chips {
    justify-content: flex-start;
}
.lp .sub-hero .scene {
    aspect-ratio: 1 / 0.82;
}
.lp .panel .rows {
    display: grid;
    gap: 10px;
}
.lp .prow {
    display: flex;
    align-items: center;
    gap: 12px;
    background: var(--lp-surface2);
    border: 1px solid var(--lp-line);
    border-radius: 12px;
    padding: 11px 14px;
    font-size: 13.5px;
    font-weight: 700;
    color: var(--lp-ink);
}
.lp .prow .pic {
    width: 30px;
    height: 30px;
    border-radius: 9px;
    display: grid;
    place-items: center;
    color: #fff;
    flex-shrink: 0;
}
.lp .prow .pic svg {
    width: 15px;
    height: 15px;
}
.lp .prow .pic.teal {
    background: linear-gradient(140deg, var(--lp-teal-bright), var(--lp-teal-deep));
}
.lp .prow .pic.coral {
    background: linear-gradient(140deg, #ff9075, var(--lp-coral-deep));
}
.lp .prow .pic.amber {
    background: linear-gradient(140deg, var(--lp-amber), #f39a12);
}
.lp .prow .pic.navy {
    background: linear-gradient(140deg, #2b4a58, var(--lp-band));
}
.lp .prow small {
    display: block;
    font-weight: 600;
    font-size: 11px;
    color: var(--lp-muted);
}
.lp .prow .amt {
    margin-left: auto;
    font-family: 'Space Mono', monospace;
    font-size: 12px;
    font-weight: 700;
    color: var(--lp-teal-deep);
    white-space: nowrap;
}
.lp .prow .amt.coral {
    color: var(--lp-coral);
}
.lp .prow.dim {
    opacity: 0.6;
}
.lp .scene .rows .prow {
    opacity: 0;
    animation: lp-pop-in 0.5s ease forwards;
}
.lp .scene .rows .prow:nth-child(1) {
    animation-delay: 0.45s;
}
.lp .scene .rows .prow:nth-child(2) {
    animation-delay: 0.6s;
}
.lp .scene .rows .prow:nth-child(3) {
    animation-delay: 0.75s;
}
.lp .scene .rows .prow:nth-child(4) {
    animation-delay: 0.9s;
}
@media (max-width: 900px) {
    .lp .sub-hero-grid {
        grid-template-columns: 1fr;
        gap: 34px;
    }
}
@media (prefers-reduced-motion: reduce) {
    .lp .scene .rows .prow {
        opacity: 1;
    }
}
`;
