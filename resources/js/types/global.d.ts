import { PageProps as InertiaPageProps } from '@inertiajs/core';
import { AxiosInstance } from 'axios';
import { route as ziggyRoute } from 'ziggy-js';
import { PageProps as AppPageProps } from './';

declare global {
    interface Window {
        axios: AxiosInstance;
    }

    /* eslint-disable no-var */
    var route: typeof ziggyRoute;

    namespace JSX {
        interface IntrinsicElements {
            // Trix mendaftarkan custom element <trix-editor>; deklarasi ini
            // memberi tahu TS atribut dasar yang kita pakai (`input`, `class`, dll).
            'trix-editor': React.DetailedHTMLProps<
                React.HTMLAttributes<HTMLElement> & {
                    input?: string;
                    placeholder?: string;
                    contenteditable?: string;
                    class?: string;
                },
                HTMLElement
            >;
        }
    }
}

declare module '@inertiajs/core' {
    interface PageProps extends InertiaPageProps, AppPageProps {}
}
