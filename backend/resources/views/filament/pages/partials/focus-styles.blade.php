{{-- Shared by the focused one-at-a-time screens (Widmen, Einzeln prüfen). --}}
<style>
    .wm { --navy: #2B3C69; --navy-soft: #4a5a85; --rose: #D989B2; --blush: #EEC6D8; --blush-light: #f8e8ef; --danger: #a8324a;
          color: var(--navy); font-family: inherit; }
    .wm-focus { position: fixed; inset: 0; z-index: 60; background: #fff; overflow-y: auto; padding: 2rem 1.5rem 3rem; }
    .wm-inner { max-width: 54rem; margin: 0 auto; }
    .wm-bar { display: flex; align-items: center; gap: 1rem; justify-content: space-between; flex-wrap: wrap; margin-bottom: 1.5rem; }
    .wm-progress { flex: 1; min-width: 12rem; }
    .wm-progress-track { height: 8px; background: var(--blush-light); border-radius: 999px; overflow: hidden; }
    .wm-progress-fill { height: 100%; background: var(--rose); transition: width .3s ease; }
    .wm-progress-label { font-size: .9rem; font-weight: 600; margin-bottom: .35rem; color: var(--navy-soft); }
    .wm-card { background: var(--blush-light); border-radius: 6px; padding: 2rem; }
    .wm-center { text-align: center; }
    .wm-stack { font-size: clamp(4rem, 12vw, 7.5rem); font-weight: 800; line-height: 1; letter-spacing: -.03em; }
    .wm-stack small { font-size: .3em; font-weight: 700; letter-spacing: .04em; text-transform: uppercase; color: var(--navy-soft); display: block; margin-bottom: .4rem; }
    .wm-meta { font-size: 1.1rem; color: var(--navy-soft); margin-top: .75rem; }
    .wm-book { font-size: 1.5rem; font-weight: 700; margin-top: .25rem; }
    .wm-dedication { background: #fff; border-radius: 6px; padding: 1.75rem 2rem; margin-top: 1.5rem; }
    .wm-label { font-size: .85rem; font-weight: 700; text-transform: uppercase; letter-spacing: .08em; color: var(--navy-soft); }
    .wm-recipient { font-size: clamp(2rem, 5vw, 3rem); font-weight: 800; line-height: 1.1; margin-top: .25rem; }
    .wm-wish { font-size: clamp(1.35rem, 3vw, 1.85rem); line-height: 1.45; margin-top: 1rem; font-style: italic; }
    .wm-sigonly { font-size: clamp(1.75rem, 4vw, 2.5rem); font-weight: 800; }
    .wm-next { margin-top: 1.25rem; font-size: 1.05rem; color: var(--navy-soft); text-align: center; }
    .wm-next strong { color: var(--navy); }
    .wm-next-new { display: inline-block; background: var(--blush); color: var(--navy); border-radius: 4px; padding: .35rem .75rem; font-weight: 700; }
    .wm-actions { display: flex; flex-wrap: wrap; gap: .75rem; justify-content: center; margin-top: 2rem; }
    .wm-btn { display: inline-flex; align-items: center; gap: .6rem; border-radius: 4px; padding: .9rem 1.4rem; font-weight: 700; font-size: 1.05rem; border: 2px solid var(--navy); background: #fff; color: var(--navy); cursor: pointer; }
    .wm-btn:hover { background: var(--blush-light); }
    .wm-btn:disabled { opacity: .45; cursor: not-allowed; }
    .wm-btn-primary { background: var(--navy); color: #fff; font-size: 1.35rem; padding: 1.1rem 2.2rem; }
    .wm-btn-primary:hover { background: var(--navy-soft); }
    .wm-btn-danger { border-color: var(--danger); color: var(--danger); }
    .wm-kbd { font-size: .75rem; font-weight: 700; border: 1.5px solid currentColor; border-radius: 3px; padding: .05rem .35rem; opacity: .75; }
    .wm-input { font-size: 3rem; font-weight: 800; text-align: center; width: 12rem; border: 3px solid var(--navy); border-radius: 6px; padding: .5rem; color: var(--navy); background: #fff; }
    .wm-input:focus { outline: 3px solid var(--rose); outline-offset: 3px; }
    .wm-h { font-size: clamp(1.6rem, 4vw, 2.2rem); font-weight: 800; line-height: 1.2; }
    .wm-p { font-size: 1.15rem; margin-top: .75rem; color: var(--navy-soft); }
    .wm-alert { border: 3px solid var(--danger); background: #fff; }
    .wm-alert .wm-h { color: var(--danger); }
    .wm-list { margin-top: 1.5rem; text-align: left; display: grid; gap: .5rem; }
    .wm-list-row { display: grid; grid-template-columns: 4.5rem 1fr; gap: 1rem; align-items: baseline; background: #fff; border-radius: 4px; padding: .6rem 1rem; }
    .wm-list-row.is-current { outline: 3px solid var(--rose); }
    .wm-list-row b { font-size: 1.4rem; }
    .wm-choice { display: grid; gap: .75rem; margin-top: 1.5rem; }
    .wm-choice button { text-align: left; }
    .wm-flag { margin-top: 1rem; display: flex; gap: .5rem; justify-content: center; flex-wrap: wrap; }
    .wm-passed { display: inline-block; background: var(--rose); color: #fff; border-radius: 4px; padding: .3rem .8rem; font-weight: 700; margin-bottom: .75rem; }
    .wm-flag input { min-width: 18rem; border: 2px solid var(--navy-soft); border-radius: 4px; padding: .6rem .8rem; color: var(--navy); }
    @media (prefers-reduced-motion: reduce) { .wm-progress-fill { transition: none; } }
</style>
