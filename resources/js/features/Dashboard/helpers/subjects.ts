import {
    Atom,
    Calculator,
    FlaskConical,
    Globe2,
    GraduationCap,
    Landmark,
    Languages,
    Leaf,
    LucideIcon,
    ShieldCheck,
} from 'lucide-react';

export type SubjectStyle = {
    icon: LucideIcon;
    /** Foreground accent (text + icon container) */
    accent: string;
    /** Soft background tint untuk container icon */
    surface: string;
    /** Hover border color */
    hoverBorder: string;
};

const STYLES: Record<string, SubjectStyle> = {
    MTK: { icon: Calculator, accent: 'text-sky-600', surface: 'bg-sky-50', hoverBorder: 'hover:border-sky-300' },
    FIS: { icon: Atom, accent: 'text-indigo-600', surface: 'bg-indigo-50', hoverBorder: 'hover:border-indigo-300' },
    KIM: { icon: FlaskConical, accent: 'text-emerald-600', surface: 'bg-emerald-50', hoverBorder: 'hover:border-emerald-300' },
    BIO: { icon: Leaf, accent: 'text-green-600', surface: 'bg-green-50', hoverBorder: 'hover:border-green-300' },
    BIND: { icon: Languages, accent: 'text-rose-600', surface: 'bg-rose-50', hoverBorder: 'hover:border-rose-300' },
    BING: { icon: Globe2, accent: 'text-amber-600', surface: 'bg-amber-50', hoverBorder: 'hover:border-amber-300' },
    SEJ: { icon: Landmark, accent: 'text-orange-600', surface: 'bg-orange-50', hoverBorder: 'hover:border-orange-300' },
    PKN: { icon: ShieldCheck, accent: 'text-teal-600', surface: 'bg-teal-50', hoverBorder: 'hover:border-teal-300' },
};

const FALLBACK: SubjectStyle = {
    icon: GraduationCap,
    accent: 'text-slate-600',
    surface: 'bg-slate-100',
    hoverBorder: 'hover:border-slate-300',
};

export function getSubjectStyle(code: string | null | undefined): SubjectStyle {
    if (!code) return FALLBACK;
    return STYLES[code.toUpperCase()] ?? FALLBACK;
}
