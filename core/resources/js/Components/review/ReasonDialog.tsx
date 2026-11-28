import { useState } from 'react';

import { TextareaField } from '@/Components/form/TextField';
import { Button } from '@/Components/ui/button';
import { Dialog, DialogClose, DialogContent } from '@/Components/ui/dialog';
import { useT } from '@/lib/i18n';

/**
 * A staff action that needs a written reason (PLAN 2.7: reject, suspend, cancel a deal, change a figure, hide a
 * photo). The confirm button stays off until the reason is written; the server checks it again and logs it.
 */
export function ReasonDialog({
    title,
    consequence,
    confirmLabel,
    tone = 'destructive',
    trigger,
    maxLength = 500,
    error,
    label,
    hint,
    onConfirm,
}: {
    title: string;
    consequence: React.ReactNode;
    confirmLabel: string;
    tone?: 'destructive' | 'default';
    trigger: React.ReactNode;
    maxLength?: number;
    error?: string | null;
    label?: string;
    hint?: string;
    onConfirm: (reason: string) => void;
}) {
    const t = useT();
    const [open, setOpen] = useState(false);
    const [reason, setReason] = useState('');
    const ready = reason.trim().length >= 3;

    return (
        <>
            <span onClick={() => setOpen(true)}>{trigger}</span>
            <Dialog
                open={open}
                onOpenChange={(next) => {
                    setOpen(next);
                    if (!next) {
                        setReason('');
                    }
                }}
            >
                <DialogContent title={title} className="space-y-4">
                    <div className="text-sm text-muted-foreground">{consequence}</div>
                    <TextareaField
                        label={label ?? t('reason.label')}
                        hint={hint ?? t('reason.hint')}
                        required
                        rows={3}
                        value={reason}
                        onChange={(value) => setReason(value.slice(0, maxLength))}
                        error={error ?? null}
                    />
                    <div className="flex flex-wrap justify-end gap-2">
                        <DialogClose asChild>
                            <Button type="button" variant="outline">
                                {t('common.cancel')}
                            </Button>
                        </DialogClose>
                        <Button
                            type="button"
                            disabled={!ready}
                            variant={tone === 'destructive' ? 'destructive' : 'default'}
                            onClick={() => {
                                setOpen(false);
                                onConfirm(reason.trim());
                                setReason('');
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
