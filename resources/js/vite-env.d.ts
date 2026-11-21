/// <reference types="vite/client" />
/// <reference types="vite-plugin-react" />

interface ImportMetaEnv {
    readonly VITE_APP_NAME: string;
    readonly VITE_PUSHER_APP_KEY: string;
    readonly VITE_PUSHER_APP_CLUSTER: string;
    readonly VITE_MAPBOX_TOKEN: string;
}

interface ImportMeta {
    readonly env: ImportMetaEnv;
    readonly glob: (pattern: string) => Record<string, () => Promise<unknown>>;
}

declare global {
    namespace JSX {
        interface IntrinsicElements {
            [elemName: string]: any;
        }
    }
}