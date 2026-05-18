import { motion } from 'framer-motion';
import { LucideIcon } from 'lucide-react';

interface Props {
    icon: LucideIcon;
    title: string;
    description?: string;
}

export default function EmptyState({ icon: Icon, title, description }: Props) {
    return (
        <motion.div
            initial={{ opacity: 0, y: 8 }}
            animate={{ opacity: 1, y: 0 }}
            transition={{ duration: 0.3, ease: [0.22, 1, 0.36, 1] }}
            className="flex flex-col items-center justify-center rounded-2xl border border-dashed border-slate-200 bg-white px-6 py-16 text-center"
        >
            <div className="mb-3 flex h-12 w-12 items-center justify-center rounded-2xl bg-slate-50 text-slate-400">
                <Icon className="h-5 w-5" />
            </div>
            <h3 className="text-sm font-semibold text-slate-700">{title}</h3>
            {description && (
                <p className="mt-1 max-w-sm text-sm text-slate-500">{description}</p>
            )}
        </motion.div>
    );
}
