import { motion } from 'framer-motion';
import { Clock } from 'lucide-react';

interface Props {
    formatted: string;
    isCritical: boolean;
    isExpired: boolean;
}

export default function ExamTimer({ formatted, isCritical, isExpired }: Props) {
    const tone = isExpired
        ? 'bg-rose-100 text-rose-700 border-rose-200'
        : isCritical
          ? 'bg-amber-100 text-amber-700 border-amber-200'
          : 'bg-slate-100 text-slate-700 border-slate-200';

    return (
        <motion.div
            animate={
                isCritical && !isExpired
                    ? { scale: [1, 1.04, 1] }
                    : { scale: 1 }
            }
            transition={{ duration: 1, repeat: isCritical ? Infinity : 0 }}
            className={`inline-flex items-center gap-2 rounded-xl border px-3 py-1.5 text-sm font-semibold tabular-nums ${tone}`}
            aria-live="polite"
        >
            <Clock className="h-4 w-4" />
            <span>{isExpired ? 'Waktu habis' : formatted}</span>
        </motion.div>
    );
}
