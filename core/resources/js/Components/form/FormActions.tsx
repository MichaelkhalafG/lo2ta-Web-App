import { Loader2 } from 'lucide-react';
import type { ReactNode } from 'react';

import { Button } from '@/Components/ui/button';
import { useT } from '@/lib/i18n';

export function FormActions({
    processing = false,
    dirty = true,
    onCancel,
    submitLabel,
    cancelLabel,
    extra,
    formId,
}: {
    processing?: boolean;
    dirty?: boolean;
    onCancel?: () => void;
    submitLabel?: string;
    cancelLabel?: string;
    extra?: ReactNode;
    formId?: string;
}) {
    const t = useT();
    const submit = submitLabel ?? t('common.save', 'حفظ');
    const cancel = cancelLabel ?? t('common.cancel', 'إلغاء');

    return (
        <div className="flex flex-wrap items-center gap-2">
            <Button type="submit" form={formId} disabled={processing || !dirty} className="gap-2">
                {processing ? <Loader2 className="h-4 w-4 animate-spin" aria-hidden="true" /> : null}
                {processing ? t('common.saving', 'جارٍ الحفظ…') : submit}
            </Button>
            {onCancel ? (
                <Button type="button" variant="outline" onClick={onCancel} disabled={processing}>
                    {cancel}
                </Button>
            ) : null}
            {dirty ? <span className="text-xs text-muted-foreground">{t('common.unsaved_changes', 'تغييرات غير محفوظة')}</span> : null}
            {extra ? <div className="ms-auto flex items-center gap-2">{extra}</div> : null}
        </div>
    );
}
