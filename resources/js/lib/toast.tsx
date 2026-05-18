import { Toaster as SonnerToaster, toast as sonnerToast } from 'sonner';

export const toast = sonnerToast;

/**
 * Mount sekali di app root. Default style sengaja minimalis untuk
 * konsisten dengan design system (rounded-xl, border slate, shadow halus).
 */
export function AppToaster() {
    return (
        <SonnerToaster
            position="top-center"
            richColors
            closeButton
            duration={4000}
            offset={16}
            toastOptions={{
                classNames: {
                    toast: 'rounded-xl border border-slate-200 shadow-sm font-sans',
                    title: 'text-sm font-medium',
                    description: 'text-xs text-slate-500',
                },
            }}
        />
    );
}
