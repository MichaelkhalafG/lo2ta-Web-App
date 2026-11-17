import { router } from '@inertiajs/react';
import { useEffect } from 'react';

import { useT } from '@/lib/i18n';

export function useDirtyGuard(dirty: boolean, message?: string): void {
    const t = useT();
    const text = message ?? t('form.unsaved_leave_confirm', 'هناك تغييرات غير محفوظة. هل تريد المتابعة وفقدانها؟');

    useEffect(() => {
        if (!dirty) {
            return;
        }

        const onBeforeUnload = (event: BeforeUnloadEvent) => {
            event.preventDefault();
            event.returnValue = '';
        };
        window.addEventListener('beforeunload', onBeforeUnload);

        const stop = router.on('before', (event) => {
            const method = event.detail.visit.method;
            if (method !== 'get') {
                return true;
            }

            return window.confirm(text);
        });

        return () => {
            window.removeEventListener('beforeunload', onBeforeUnload);
            stop();
        };
    }, [dirty, text]);
}
