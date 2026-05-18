import { usePage } from '@inertiajs/react';
import { useEffect } from 'react';
import { PageProps } from '@/types';
import { toast } from './toast';

/**
 * Auto-show toast saat backend mengirim flash success/error via session.
 * Panggil sekali di tiap layout (atau di setiap page yang tidak pakai layout).
 */
export function useFlashToast() {
    const { flash } = usePage<PageProps>().props;

    useEffect(() => {
        if (flash?.success) {
            toast.success(flash.success);
        }
        if (flash?.error) {
            toast.error(flash.error);
        }
    }, [flash?.success, flash?.error]);
}
