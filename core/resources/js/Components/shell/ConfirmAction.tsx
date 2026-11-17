import { useState } from 'react';

import { Button } from '@/Components/ui/button';
import { Dialog, DialogClose, DialogContent } from '@/Components/ui/dialog';
import { useT } from '@/lib/i18n';

export function ConfirmAction({
    title,
    consequence,
    confirmLabel,
    tone = 'destructive',
    disabled = false,
    trigger,
    onConfirm,
}: {
    title: string;
    consequence: React.ReactNode;
    confirmLabel: string;
    tone?: 'destructive' | 'default';
    disabled?: boolean;
    trigger: React.ReactNode;
    onConfirm: () => void;
}) {
    const t = useT();
    const [open, setOpen] = useState(false);

    if (disabled) {
        return <>{trigger}</>;
    }

    return (
        <>
            <span onClick={() => setOpen(true)}>{trigger}</span>

            <Dialog open={open} onOpenChange={setOpen}>
                <DialogContent title={title} className="space-y-4">
                    <div className="text-sm text-muted-foreground">{consequence}</div>
                    <div className="flex flex-wrap justify-end gap-2">
                        <DialogClose asChild>
                            <Button type="button" variant="outline">
                                {t('common.cancel', 'إلغاء')}
                            </Button>
                        </DialogClose>
                        <Button
                            type="button"
                            variant={tone === 'destructive' ? 'destructive' : 'default'}
                            onClick={() => {
                                setOpen(false);
                                onConfirm();
                            }}
                        >
                            {confirmLabel}
                        </Button>
                    </div>
                </DialogContent>
            </Dialog>
        </>
    );
}
