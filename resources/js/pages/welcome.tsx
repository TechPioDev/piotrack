import { useAppearance } from '@/hooks/use-appearance';
import { type SharedData } from '@/types';
import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';

/**
 * The public marketing page — a modern, animated product landing page for
 * Piotrack, the growth OS for MSPs. This is deliberately vibrant and playful
 * (its job is to convert visitors); the authenticated app stays clean and
 * data-dense. Everything is scoped under `.lp` with `--lp-*` tokens so nothing
 * leaks into the rest of the app, and the display faces are self-hosted (see
 * app.css) so the page renders identically on the air-gapped server.
 */

const styles = `
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
`;

/** Footer newsletter signup — a real list (POST /newsletter), not decoration. */
function NewsletterForm() {
    const { flash } = usePage<SharedData>().props;
    const form = useForm({ email: '', website: '' });
    const [submitted, setSubmitted] = useState(false);

    if (submitted && flash?.status) {
        return <p className="news-done">{flash.status}</p>;
    }

    return (
        <form
            className="news-form"
            onSubmit={(e) => {
                e.preventDefault();
                form.post(route('newsletter.subscribe'), {
                    preserveScroll: true,
                    onSuccess: () => setSubmitted(true),
                });
            }}
        >
            {/* Honeypot — humans never see it. */}
            <input
                type="text"
                name="website"
                value={form.data.website}
                onChange={(e) => form.setData('website', e.target.value)}
                tabIndex={-1}
                autoComplete="off"
                aria-hidden="true"
                style={{ position: 'absolute', left: -9999, width: 1, height: 1 }}
            />
            <div className="news-row">
                <input
                    type="email"
                    required
                    placeholder="you@yourmsp.com"
                    aria-label="Email address for the newsletter"
                    value={form.data.email}
                    onChange={(e) => form.setData('email', e.target.value)}
                />
                <button type="submit" disabled={form.processing}>
                    {form.processing ? 'Subscribing…' : 'Subscribe'}
                </button>
            </div>
            {form.errors.email && <p className="news-error">{form.errors.email}</p>}
        </form>
    );
}

export default function Welcome() {
    const { auth } = usePage<SharedData>().props;
    const { updateAppearance } = useAppearance();
    const rootRef = useRef<HTMLDivElement>(null);
    const [isDark, setIsDark] = useState(false);

    useEffect(() => {
        setIsDark(document.documentElement.classList.contains('dark'));
    }, []);

    const toggleTheme = () => {
        const nowDark = document.documentElement.classList.contains('dark');
        updateAppearance(nowDark ? 'light' : 'dark');
        setIsDark(!nowDark);
    };

    useEffect(() => {
        const root = rootRef.current;
        if (!root) {
            return;
        }

        // Reveal sections as they scroll into view. The hero load sequence is
        // pure CSS (see the stylesheet), so it needs no JS here.
        const revealObserver = new IntersectionObserver(
            (entries) => {
                entries.forEach((entry) => {
                    if (entry.isIntersecting) {
                        entry.target.classList.add('in');
                        revealObserver.unobserve(entry.target);
                    }
                });
            },
            { threshold: 0.14 },
        );
        root.querySelectorAll<HTMLElement>('.reveal').forEach((el) => revealObserver.observe(el));

        // Count the headline stats up when they land on screen.
        const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        const format = (value: number, decimals: number) =>
            value.toLocaleString('en-US', { minimumFractionDigits: decimals, maximumFractionDigits: decimals });

        const countObserver = new IntersectionObserver(
            (entries) => {
                entries.forEach((entry) => {
                    if (!entry.isIntersecting) {
                        return;
                    }
                    const el = entry.target as HTMLElement;
                    countObserver.unobserve(el);

                    const target = parseFloat(el.dataset.count ?? '0');
                    const decimals = parseInt(el.dataset.dec ?? '0', 10);
                    const prefix = el.dataset.prefix ?? '';
                    const suffix = el.dataset.suffix ?? '';

                    if (reduceMotion) {
                        el.textContent = prefix + format(target, decimals) + suffix;
                        return;
                    }

                    const duration = 1400;
                    const start = performance.now();
                    const tick = (now: number) => {
                        const progress = Math.min((now - start) / duration, 1);
                        const eased = 1 - Math.pow(1 - progress, 3);
                        el.textContent = prefix + format(target * eased, decimals) + suffix;
                        if (progress < 1) {
                            requestAnimationFrame(tick);
                        }
                    };
                    requestAnimationFrame(tick);
                });
            },
            { threshold: 0.4 },
        );
        root.querySelectorAll<HTMLElement>('[data-count]').forEach((el) => countObserver.observe(el));

        // Thicken the sticky nav border once the page is scrolled.
        const nav = root.querySelector<HTMLElement>('.nav');
        const onScroll = () => nav?.classList.toggle('scrolled', window.scrollY > 8);
        window.addEventListener('scroll', onScroll, { passive: true });

        return () => {
            revealObserver.disconnect();
            countObserver.disconnect();
            window.removeEventListener('scroll', onScroll);
        };
    }, []);

    return (
        <>
            <Head title="Piotrack — the growth OS for MSPs" />
            <style>{styles}</style>

            <div className="lp" ref={rootRef}>
                <header className="nav">
                    <div className="wrap nav-inner">
                        <a className="brand" href="#top">
                            <span className="mark" aria-hidden="true">
                                <svg viewBox="0 0 24 24" fill="none" stroke="#fff" strokeWidth="2.4" strokeLinecap="round" strokeLinejoin="round">
                                    <path d="M3 17l5-5 4 3 8-9" />
                                    <path d="M15 6h5v5" />
                                </svg>
                            </span>
                            Piotrack
                        </a>
                        <nav className="nav-links">
                            <a href="#features">Platform</a>
                            <a href="#how">How it works</a>
                            <a href="#proof">Results</a>
                        </nav>
                        <div className="nav-cta">
                            <button className="theme-toggle" onClick={toggleTheme} aria-label="Toggle theme" title="Toggle theme" type="button">
                                {isDark ? (
                                    <svg
                                        viewBox="0 0 24 24"
                                        fill="none"
                                        stroke="currentColor"
                                        strokeWidth="2"
                                        strokeLinecap="round"
                                        strokeLinejoin="round"
                                    >
                                        <path d="M21 12.8A9 9 0 1 1 11.2 3a7 7 0 0 0 9.8 9.8z" />
                                    </svg>
                                ) : (
                                    <svg
                                        viewBox="0 0 24 24"
                                        fill="none"
                                        stroke="currentColor"
                                        strokeWidth="2"
                                        strokeLinecap="round"
                                        strokeLinejoin="round"
                                    >
                                        <circle cx="12" cy="12" r="4" />
                                        <path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4" />
                                    </svg>
                                )}
                            </button>
                            {auth.user ? (
                                <Link className="btn btn-primary" href={route('dashboard')}>
                                    Go to dashboard
                                </Link>
                            ) : (
                                <>
                                    <Link className="login-link" href={route('login')}>
                                        Log in
                                    </Link>
                                    <Link className="btn btn-primary" href={route('register')}>
                                        Start free
                                    </Link>
                                </>
                            )}
                        </div>
                    </div>
                </header>

                <main id="top">
                    <section className="hero">
                        <div className="wrap hero-grid">
                            <div className="hero-copy">
                                <span className="badge-row reveal">
                                    <span className="pill">MSP GROWTH OS</span>
                                    Marketing · Sales · AI visibility, unified
                                </span>
                                <h1 className="reveal">
                                    Turn scattered marketing into{' '}
                                    <span className="hl">
                                        predictable pipeline
                                        <svg viewBox="0 0 300 20" preserveAspectRatio="none" aria-hidden="true">
                                            <path d="M4 14 Q 80 4 150 10 T 296 6" />
                                        </svg>
                                    </span>
                                    .
                                </h1>
                                <p className="lead reveal">
                                    Piotrack is the growth platform built for MSPs — SEO, ads, content, CRM and AI visibility in one place, with
                                    revenue attribution that proves exactly what&apos;s working.
                                </p>
                                <div className="hero-cta reveal">
                                    <Link className="btn btn-primary" href={auth.user ? route('dashboard') : route('register')}>
                                        Start free
                                        <svg
                                            viewBox="0 0 24 24"
                                            fill="none"
                                            stroke="currentColor"
                                            strokeWidth="2.4"
                                            strokeLinecap="round"
                                            strokeLinejoin="round"
                                        >
                                            <path d="M5 12h14M13 6l6 6-6 6" />
                                        </svg>
                                    </Link>
                                    <a className="btn btn-ghost" href="#proof">
                                        <svg
                                            viewBox="0 0 24 24"
                                            fill="none"
                                            stroke="currentColor"
                                            strokeWidth="2.2"
                                            strokeLinecap="round"
                                            strokeLinejoin="round"
                                        >
                                            <path d="M8 5v14l11-7z" />
                                        </svg>
                                        See a live demo
                                    </a>
                                </div>
                                <p className="hero-note reveal">
                                    <svg
                                        viewBox="0 0 24 24"
                                        fill="none"
                                        stroke="currentColor"
                                        strokeWidth="2.6"
                                        strokeLinecap="round"
                                        strokeLinejoin="round"
                                    >
                                        <path d="M20 6L9 17l-5-5" />
                                    </svg>
                                    No credit card · Live demo data included
                                </p>
                            </div>

                            <div className="scene" aria-hidden="true">
                                <div className="blob" />

                                <div className="spark">
                                    <svg viewBox="0 0 60 60" fill="none">
                                        <circle cx="30" cy="30" r="26" fill="#FF6B54" />
                                        <path d="M30 16c6 3 9 8 9 15 0 3-1 6-3 8l-2-3-2 3-2-3-2 3-2-3-2 3c-2-2-3-5-3-8 0-7 3-12 9-15z" fill="#fff" />
                                        <circle cx="30" cy="27" r="3" fill="#FF6B54" />
                                    </svg>
                                </div>

                                <div className="panel">
                                    <div className="panel-top">
                                        <i />
                                        <i />
                                        <i />
                                        <span>piotrack · growth</span>
                                    </div>
                                    <div className="panel-body">
                                        <div className="kpi-row">
                                            <div className="kpi">
                                                <div className="l">Pipeline</div>
                                                <div className="v teal">$486K</div>
                                            </div>
                                            <div className="kpi">
                                                <div className="l">New MRR</div>
                                                <div className="v">$31.4K</div>
                                            </div>
                                            <div className="kpi">
                                                <div className="l">ROAS</div>
                                                <div className="v coral">4.57x</div>
                                            </div>
                                        </div>
                                        <div className="chart">
                                            <svg viewBox="0 0 320 128" preserveAspectRatio="none">
                                                <defs>
                                                    <linearGradient id="lpAreaFill" x1="0" y1="0" x2="0" y2="1">
                                                        <stop offset="0%" stopColor="var(--lp-teal)" stopOpacity="0.28" />
                                                        <stop offset="100%" stopColor="var(--lp-teal)" stopOpacity="0" />
                                                    </linearGradient>
                                                </defs>
                                                <g className="grid">
                                                    <line x1="0" y1="32" x2="320" y2="32" />
                                                    <line x1="0" y1="64" x2="320" y2="64" />
                                                    <line x1="0" y1="96" x2="320" y2="96" />
                                                </g>
                                                <g className="bars">
                                                    <rect x="18" y="78" width="20" height="46" rx="4" />
                                                    <rect x="78" y="66" width="20" height="58" rx="4" />
                                                    <rect x="138" y="72" width="20" height="52" rx="4" />
                                                    <rect x="198" y="48" width="20" height="76" rx="4" />
                                                    <rect x="258" y="30" width="20" height="94" rx="4" />
                                                </g>
                                                <path className="area" d="M8 96 L 72 84 L 140 72 L 208 50 L 300 26 L 300 128 L 8 128 Z" />
                                                <path className="line" d="M8 96 L 72 84 L 140 72 L 208 50 L 300 26" />
                                                <circle className="dot" cx="300" cy="26" r="6" />
                                            </svg>
                                        </div>
                                    </div>
                                </div>

                                <div className="float f1">
                                    <span className="ic">
                                        <svg
                                            viewBox="0 0 24 24"
                                            fill="none"
                                            stroke="currentColor"
                                            strokeWidth="2.2"
                                            strokeLinecap="round"
                                            strokeLinejoin="round"
                                        >
                                            <path d="M12 2l2.4 7.4H22l-6 4.4 2.3 7.2-6.3-4.6L5.7 21l2.3-7.2-6-4.4h7.6z" />
                                        </svg>
                                    </span>
                                    <div>
                                        Hot lead 🔥<small>Michael · Precision Mfg</small>
                                    </div>
                                </div>
                                <div className="float f2">
                                    <span className="ic">
                                        <svg
                                            viewBox="0 0 24 24"
                                            fill="none"
                                            stroke="currentColor"
                                            strokeWidth="2.2"
                                            strokeLinecap="round"
                                            strokeLinejoin="round"
                                        >
                                            <path d="M12 1v22M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6" />
                                        </svg>
                                    </span>
                                    <div>
                                        +$4,500 MRR<small>Closed won · attributed</small>
                                    </div>
                                </div>
                                <div className="float f3">
                                    <span className="ic">
                                        <svg
                                            viewBox="0 0 24 24"
                                            fill="none"
                                            stroke="currentColor"
                                            strokeWidth="2.2"
                                            strokeLinecap="round"
                                            strokeLinejoin="round"
                                        >
                                            <path d="M12 2a10 10 0 1 0 10 10" />
                                            <path d="M12 6a6 6 0 1 0 6 6" />
                                            <circle cx="12" cy="12" r="1.6" fill="currentColor" stroke="none" />
                                        </svg>
                                    </span>
                                    <div>
                                        #2 in ChatGPT<small>&quot;Best MSP Philadelphia&quot;</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </section>

                    <section className="trust">
                        <div className="wrap trust-inner reveal">
                            <span>Built for</span>
                            <span className="dot" />
                            <span>Managed IT</span>
                            <span className="dot" />
                            <span>Cybersecurity</span>
                            <span className="dot" />
                            <span>Microsoft 365</span>
                            <span className="dot" />
                            <span>Co-managed IT</span>
                            <span className="dot" />
                            <span>CMMC &amp; compliance</span>
                        </div>
                    </section>

                    <section className="stats" id="proof">
                        <div className="wrap">
                            <div className="stats-grid reveal">
                                <div className="stat">
                                    <div className="num" data-count="4.57" data-suffix="x" data-dec="2">
                                        0
                                    </div>
                                    <div className="cap">Return on ad spend</div>
                                </div>
                                <div className="stat">
                                    <div className="num" data-count="54" data-prefix="$" data-suffix="K">
                                        0
                                    </div>
                                    <div className="cap">ARR attributed, live</div>
                                </div>
                                <div className="stat">
                                    <div className="num" data-count="9">
                                        0
                                    </div>
                                    <div className="cap">Growth channels, unified</div>
                                </div>
                                <div className="stat">
                                    <div className="num" data-count="1142">
                                        0
                                    </div>
                                    <div className="cap">Features, one login</div>
                                </div>
                            </div>
                        </div>
                    </section>

                    <section className="sec" id="features">
                        <div className="wrap">
                            <div className="sec-head reveal">
                                <span className="eyebrow">One platform</span>
                                <h2>Every growth channel, talking to each other.</h2>
                                <p>
                                    Stop stitching together six tools that never agree. Piotrack runs the whole funnel and connects every dollar back
                                    to the campaign that earned it.
                                </p>
                            </div>
                            <div className="features">
                                <article className="card reveal">
                                    <div className="ico c-teal">
                                        <svg
                                            viewBox="0 0 24 24"
                                            fill="none"
                                            stroke="currentColor"
                                            strokeWidth="2.2"
                                            strokeLinecap="round"
                                            strokeLinejoin="round"
                                        >
                                            <path d="M3 3v18h18" />
                                            <path d="M7 15l4-5 3 3 5-7" />
                                        </svg>
                                    </div>
                                    <h3>Revenue attribution</h3>
                                    <p>
                                        Trace a closed deal back through the meeting, the lead, the campaign and the keyword that started it. See real
                                        ROAS, CAC and pipeline — not vanity clicks.
                                    </p>
                                    <span className="tag">source → $ won</span>
                                </article>
                                <article className="card reveal">
                                    <div className="ico c-coral">
                                        <svg
                                            viewBox="0 0 24 24"
                                            fill="none"
                                            stroke="currentColor"
                                            strokeWidth="2.2"
                                            strokeLinecap="round"
                                            strokeLinejoin="round"
                                        >
                                            <path d="M12 3a9 9 0 1 0 9 9" />
                                            <path d="M12 7a5 5 0 1 0 5 5" />
                                            <circle cx="12" cy="12" r="1.5" fill="currentColor" stroke="none" />
                                        </svg>
                                    </div>
                                    <h3>AI visibility</h3>
                                    <p>
                                        Track how ChatGPT, Gemini and Perplexity answer &quot;best MSP near me&quot; — your mentions, citations and
                                        share of voice against competitors, over time.
                                    </p>
                                    <span className="tag">answer-engine ranking</span>
                                </article>
                                <article className="card reveal">
                                    <div className="ico c-amber">
                                        <svg
                                            viewBox="0 0 24 24"
                                            fill="none"
                                            stroke="currentColor"
                                            strokeWidth="2.2"
                                            strokeLinecap="round"
                                            strokeLinejoin="round"
                                        >
                                            <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2" />
                                            <circle cx="9" cy="7" r="4" />
                                            <path d="M23 21v-2a4 4 0 0 0-3-3.87M16 3.13A4 4 0 0 1 16 11" />
                                        </svg>
                                    </div>
                                    <h3>CRM &amp; pipeline</h3>
                                    <p>
                                        Contacts, companies, a real kanban pipeline and lead scoring that turns hot into a sales alert the moment it
                                        happens — no data-entry busywork.
                                    </p>
                                    <span className="tag">lead → close</span>
                                </article>
                                <article className="card reveal">
                                    <div className="ico c-cyan">
                                        <svg
                                            viewBox="0 0 24 24"
                                            fill="none"
                                            stroke="currentColor"
                                            strokeWidth="2.2"
                                            strokeLinecap="round"
                                            strokeLinejoin="round"
                                        >
                                            <circle cx="11" cy="11" r="8" />
                                            <path d="M21 21l-4.3-4.3" />
                                        </svg>
                                    </div>
                                    <h3>SEO &amp; local</h3>
                                    <p>
                                        Rank tracking, technical audits, keyword clusters and per-location visibility for every branch you serve —
                                        with recommendations you can act on today.
                                    </p>
                                    <span className="tag">rankings + audits</span>
                                </article>
                                <article className="card reveal">
                                    <div className="ico c-navy">
                                        <svg
                                            viewBox="0 0 24 24"
                                            fill="none"
                                            stroke="currentColor"
                                            strokeWidth="2.2"
                                            strokeLinecap="round"
                                            strokeLinejoin="round"
                                        >
                                            <path d="M22 2L11 13" />
                                            <path d="M22 2l-7 20-4-9-9-4 20-7z" />
                                        </svg>
                                    </div>
                                    <h3>Campaigns &amp; automation</h3>
                                    <p>
                                        Landing pages, forms, email and multi-step nurture workflows that fire on real behavior — every send
                                        suppression-safe and tracked end to end.
                                    </p>
                                    <span className="tag">nurture on autopilot</span>
                                </article>
                                <article className="card reveal">
                                    <div className="ico c-pink">
                                        <svg
                                            viewBox="0 0 24 24"
                                            fill="none"
                                            stroke="currentColor"
                                            strokeWidth="2.2"
                                            strokeLinecap="round"
                                            strokeLinejoin="round"
                                        >
                                            <path d="M12 2l2.4 7.4H22l-6 4.4 2.3 7.2-6.3-4.6L5.7 21l2.3-7.2-6-4.4h7.6z" />
                                        </svg>
                                    </div>
                                    <h3>Growth score</h3>
                                    <p>
                                        One number that grades your whole growth engine across ten weighted areas — and tells you the single weakest
                                        link to fix next.
                                    </p>
                                    <span className="tag">0–100, benchmarked</span>
                                </article>
                            </div>
                        </div>
                    </section>

                    <section className="sec" id="how" style={{ paddingTop: 0 }}>
                        <div className="wrap">
                            <div className="sec-head center reveal">
                                <span className="eyebrow">How it works</span>
                                <h2>From first click to closed deal — one thread.</h2>
                            </div>
                            <div className="flow">
                                <div className="step reveal">
                                    <span className="arrow">
                                        <svg
                                            viewBox="0 0 24 24"
                                            fill="none"
                                            stroke="currentColor"
                                            strokeWidth="2.4"
                                            strokeLinecap="round"
                                            strokeLinejoin="round"
                                        >
                                            <path d="M5 12h14M13 6l6 6-6 6" />
                                        </svg>
                                    </span>
                                    <div className="n" />
                                    <h3>Capture</h3>
                                    <p>
                                        A visitor finds you through search, ads or an AI answer, lands on a Piotrack page and becomes a scored lead in
                                        your CRM automatically.
                                    </p>
                                </div>
                                <div className="step reveal">
                                    <span className="arrow">
                                        <svg
                                            viewBox="0 0 24 24"
                                            fill="none"
                                            stroke="currentColor"
                                            strokeWidth="2.4"
                                            strokeLinecap="round"
                                            strokeLinejoin="round"
                                        >
                                            <path d="M5 12h14M13 6l6 6-6 6" />
                                        </svg>
                                    </span>
                                    <div className="n" />
                                    <h3>Qualify</h3>
                                    <p>
                                        Scoring and buyer-intent signals promote the lead to sales-ready and fire an alert, so your rep reaches the
                                        hot ones while they&apos;re still warm.
                                    </p>
                                </div>
                                <div className="step reveal">
                                    <div className="n" />
                                    <h3>Attribute</h3>
                                    <p>
                                        The deal closes and the revenue snaps back to the exact source — proving what to double down on and what to
                                        stop paying for.
                                    </p>
                                </div>
                            </div>
                        </div>
                    </section>

                    <section className="wrap" id="cta">
                        <div className="cta-band reveal">
                            <h2>See your growth engine in one place.</h2>
                            <p>
                                Spin up a workspace loaded with live demo data and click through the whole funnel — pipeline, campaigns, attribution
                                and AI visibility — in minutes.
                            </p>
                            <div className="hero-cta" style={{ justifyContent: 'center' }}>
                                <Link className="btn btn-primary" href={auth.user ? route('dashboard') : route('register')}>
                                    Start free
                                    <svg
                                        viewBox="0 0 24 24"
                                        fill="none"
                                        stroke="currentColor"
                                        strokeWidth="2.4"
                                        strokeLinecap="round"
                                        strokeLinejoin="round"
                                    >
                                        <path d="M5 12h14M13 6l6 6-6 6" />
                                    </svg>
                                </Link>
                                <Link className="btn btn-ghost" href={route('login')}>
                                    Log in
                                </Link>
                            </div>
                        </div>
                    </section>
                </main>

                <footer className="foot">
                    <div className="wrap">
                        <div className="foot-grid">
                            <div className="foot-brand">
                                <a className="brand" href="#top" style={{ fontSize: 18 }}>
                                    <span className="mark" aria-hidden="true">
                                        <svg
                                            viewBox="0 0 24 24"
                                            fill="none"
                                            stroke="#fff"
                                            strokeWidth="2.4"
                                            strokeLinecap="round"
                                            strokeLinejoin="round"
                                        >
                                            <path d="M3 17l5-5 4 3 8-9" />
                                            <path d="M15 6h5v5" />
                                        </svg>
                                    </span>
                                    Piotrack
                                </a>
                                <p>
                                    The growth operating system for managed service providers — marketing, sales, attribution and AI visibility,
                                    measured to revenue in one place.
                                </p>
                            </div>

                            <nav className="foot-col" aria-label="Product">
                                <h4>Product</h4>
                                <a href="#features">Platform</a>
                                <a href="#how">How it works</a>
                                <a href="#proof">Results</a>
                                <Link href={route('login')}>Log in</Link>
                                <Link href={route('register')}>Start free</Link>
                            </nav>

                            <nav className="foot-col" aria-label="Resources">
                                <h4>Resources</h4>
                                <a href="/piotrack-user-guide.pdf" target="_blank" rel="noopener noreferrer">
                                    User Guide (PDF)
                                </a>
                                <a href="#features">Website chat &amp; booking</a>
                                <a href="#features">AI visibility tracking</a>
                                <a href="#features">Revenue attribution</a>
                            </nav>

                            <div className="foot-col foot-news">
                                <h4>MSP growth insights</h4>
                                <p>Practical notes on pipeline, SEO and AI visibility for MSPs. No spam, unsubscribe anytime.</p>
                                <NewsletterForm />
                            </div>
                        </div>

                        <div className="foot-bottom">
                            <span className="mono" style={{ fontSize: 12.5 }}>
                                © 2026 Piotrack
                            </span>
                            <span>Built for MSPs. Measured to revenue.</span>
                        </div>
                    </div>
                </footer>
            </div>
        </>
    );
}
