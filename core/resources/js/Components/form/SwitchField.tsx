import { Field, type FieldShellProps } from '@/Components/form/Field';
import { Switch } from '@/Components/ui/switch';

export function SwitchField({
    checked,
    onChange,
    disabled,
    ...shell
}: FieldShellProps & { checked: boolean; onChange: (checked: boolean) => void; disabled?: boolean }) {
    return (
        <Field
            {...shell}
            inline
            render={(attrs) => (
                <Switch {...{ ...attrs, required: undefined }} checked={checked} disabled={disabled} onCheckedChange={onChange} />
            )}
        />
    );
}
