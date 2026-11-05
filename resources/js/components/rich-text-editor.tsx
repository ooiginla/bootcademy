import {
    Bold,
    Heading2,
    Italic,
    Link2,
    List,
    ListOrdered,
    RemoveFormatting,
    Underline,
} from 'lucide-react';
import { useRef, useState } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';

type RichTextEditorProps = {
    id: string;
    name: string;
    label: string;
    defaultValue?: string | null;
    placeholder: string;
    error?: string;
};

export function RichTextEditor({
    id,
    name,
    label,
    defaultValue = '',
    placeholder,
    error,
}: RichTextEditorProps) {
    const editor = useRef<HTMLDivElement>(null);
    const [value, setValue] = useState(defaultValue ?? '');

    const command = (name: string, commandValue?: string) => {
        editor.current?.focus();
        document.execCommand(name, false, commandValue);
        setValue(editor.current?.innerHTML ?? '');
    };

    const addLink = () => {
        const url = window.prompt('Enter a secure link (https://…)');
        if (url) command('createLink', url);
    };

    const tools = [
        {
            label: 'Heading',
            icon: Heading2,
            action: () => command('formatBlock', 'h3'),
        },
        { label: 'Bold', icon: Bold, action: () => command('bold') },
        { label: 'Italic', icon: Italic, action: () => command('italic') },
        {
            label: 'Underline',
            icon: Underline,
            action: () => command('underline'),
        },
        {
            label: 'Bulleted list',
            icon: List,
            action: () => command('insertUnorderedList'),
        },
        {
            label: 'Numbered list',
            icon: ListOrdered,
            action: () => command('insertOrderedList'),
        },
        { label: 'Link', icon: Link2, action: addLink },
        {
            label: 'Clear formatting',
            icon: RemoveFormatting,
            action: () => command('removeFormat'),
        },
    ];

    return (
        <div className="grid gap-2">
            <Label htmlFor={id}>{label}</Label>
            <div className="overflow-hidden rounded-lg border bg-background focus-within:ring-2 focus-within:ring-ring/40">
                <div
                    className="flex flex-wrap gap-1 border-b bg-muted/40 p-2"
                    role="toolbar"
                    aria-label={`${label} formatting`}
                >
                    {tools.map(({ label: toolLabel, icon: Icon, action }) => (
                        <Button
                            key={toolLabel}
                            type="button"
                            variant="ghost"
                            size="icon"
                            className="size-8"
                            aria-label={toolLabel}
                            title={toolLabel}
                            onClick={action}
                        >
                            <Icon className="size-4" />
                        </Button>
                    ))}
                </div>
                <div className="relative">
                    {!value.replace(/<[^>]*>/g, '').trim() && (
                        <span className="pointer-events-none absolute top-3 left-3 text-sm text-muted-foreground">
                            {placeholder}
                        </span>
                    )}
                    <div
                        id={id}
                        ref={editor}
                        contentEditable
                        suppressContentEditableWarning
                        role="textbox"
                        aria-multiline="true"
                        aria-label={label}
                        className="min-h-32 px-3 py-2 text-sm leading-6 outline-none [&_a]:text-primary [&_a]:underline [&_blockquote]:border-l-2 [&_blockquote]:pl-3 [&_h3]:text-base [&_h3]:font-semibold [&_ol]:list-decimal [&_ol]:pl-6 [&_ul]:list-disc [&_ul]:pl-6"
                        dangerouslySetInnerHTML={{ __html: defaultValue ?? '' }}
                        onInput={(event) =>
                            setValue(event.currentTarget.innerHTML)
                        }
                    />
                </div>
            </div>
            <input type="hidden" name={name} value={value} />
            <InputError message={error} />
        </div>
    );
}
