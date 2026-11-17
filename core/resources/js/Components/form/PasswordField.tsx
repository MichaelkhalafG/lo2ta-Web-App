import { Eye, EyeOff } from 'lucide-react';
import { useState } from 'react';

import { Field, type FieldShellProps } from '@/Components/form/Field';
import { Input } from '@/Components/ui/input';
import { useT } from '@/lib/i18n';

export function PasswordField({
    value,
    onChange,
    autoComplete,
    ...shell
}: FieldShellProps & {
    value: string;
    onChange: (value: string) => void;
    autoComplete?: string;
}) {
    const t = useT();
    const [revealed, setRevealed] = useState(false);
    const Icon = revealed ? EyeOff : Eye;

    return (
        <Field
            {...shell}
            render={(attrs) => (
                <div className="relative">
                    <Input
                        {...attrs}
                        type={revealed ? 'text' : 'password'}
                        dir="ltr"
                        className="pe-10"
                        autoComplete={autoComplete}
                        value={value}
                        onChange={(event) => onChange(event.target.value)}
                    />

                    <button
                        type="button"
                        onClick={() => setRevealed((current) => !current)}
                        aria-pressed={revealed}
                        aria-label={t('auth.show_password', 'إظهار كلمة المرور')}
                        title={
                            revealed
                                ? t('auth.hide_password', 'إخفاء كلمة المرور')
                                : t('auth.show_password', 'إظهار كلمة المرور')
                        }
                        className="absolute inset-y-0 end-0 flex w-10 items-center justify-center text-muted-foreground transition-colors hover:text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                    >
                        <Icon className="h-4 w-4" aria-hidden="true" />
                    </button>
                </div>
            )}
        />
    );
}
