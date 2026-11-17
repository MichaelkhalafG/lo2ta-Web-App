import { Field, type FieldShellProps } from '@/Components/form/Field';
import { Input, Textarea } from '@/Components/ui/input';
import { Badge } from '@/Components/ui/badge';
import { cn } from '@/lib/utils';
import { useT } from '@/lib/i18n';

export type Translations = Record<string, string>;

export function TranslatedField({
    name,
    value,
    onChange,
    errors = {},
    locales = ['ar', 'en'],
    multiline = false,
    required = false,
    ...shell
}: Omit<FieldShellProps, 'error'> & {
    name: string;
    value: Translations;
    onChange: (value: Translations) => void;
    errors?: Record<string, string>;
    locales?: string[];
    multiline?: boolean;
    required?: boolean;
}) {
    const t = useT();
    const LABELS: Record<string, string> = { ar: 'العربية', en: 'English' };

    return (
        <div className="space-y-2">
            <div className="flex items-center gap-2">
                <span className="text-sm font-medium">{shell.label}</span>
                {required ? (
                    <span className="text-destructive" aria-hidden="true">
                        *
                    </span>
                ) : null}
            </div>

            <div className="grid gap-3 sm:grid-cols-2">
                {locales.map((locale) => {
                    const error = errors[`${name}.${locale}`] ?? null;
                    const rtl = locale === 'ar';

                    return (
                        <Field
                            key={locale}
                            label={LABELS[locale] ?? locale}
                            error={error}
                            required={required}
                            className={cn('rounded-lg border bg-muted/30 p-3', error !== null && 'border-destructive/50')}
                            render={(attrs) =>
                                multiline ? (
                                    <Textarea
                                        {...attrs}
                                        dir={rtl ? 'rtl' : 'ltr'}
                                        lang={locale}
                                        value={value[locale] ?? ''}
                                        onChange={(event) => onChange({ ...value, [locale]: event.target.value })}
                                    />
                                ) : (
                                    <Input
                                        {...attrs}
                                        dir={rtl ? 'rtl' : 'ltr'}
                                        lang={locale}
                                        value={value[locale] ?? ''}
                                        onChange={(event) => onChange({ ...value, [locale]: event.target.value })}
                                    />
                                )
                            }
                        />
                    );
                })}
            </div>

            {shell.hint ? <p className="text-xs text-muted-foreground">{shell.hint}</p> : null}
            {locales.some((locale) => (value[locale] ?? '') === '') ? (
                <Badge variant="warning">
                    {locales
                        .filter((locale) => (value[locale] ?? '') === '')
                        .map((locale) => LABELS[locale] ?? locale)
                        .join(' · ')}{' '}
                    {t('form.missing', 'ناقص')}
                </Badge>
            ) : null}
        </div>
    );
}
