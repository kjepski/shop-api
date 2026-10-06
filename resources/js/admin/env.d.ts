/// <reference types="vite/client" />

// For plain TypeScript (typed ESLint rules), which cannot read .vue files.
// vue-tsc resolves the real component types and does not use this declaration.
declare module '*.vue' {
    import type { DefineComponent } from 'vue';
    const component: DefineComponent<object, object, unknown>;
    export default component;
}
