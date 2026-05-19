import { Link } from '@inertiajs/react';
import { motion } from 'framer-motion';
import {
    BookOpen,
    ChevronRight,
    ClipboardList,
    FileSpreadsheet,
    FileText,
    LucideIcon,
    Paperclip,
    Link as LinkIcon,
} from 'lucide-react';
import { fadeUp } from '@/lib/motion';
import type { MaterialListItem } from './course.type';

interface Props {
    courseId: string;
    material: MaterialListItem;
}

function formatDate(iso: string | null) {
    if (!iso) return null;
    return new Date(iso).toLocaleDateString('id-ID', {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
    });
}

/**
 * Pilih icon & warna utama berdasar "isi dominan" material:
 * - Ujian saja  → FileSpreadsheet (ungu)
 * - Tugas saja  → ClipboardList (amber)
 * - Default     → FileText / BookOpen (sky)
 *
 * Tujuannya: dari list, siswa langsung tahu "ini blok latihan / blok ujian / blok bacaan".
 */
function pickAccent(m: MaterialListItem): { icon: LucideIcon; surface: string; accent: string } {
    const examOnly = m.exam_count > 0 && m.assignment_count === 0 && !m.has_content;
    const assignmentOnly = m.assignment_count > 0 && m.exam_count === 0 && !m.has_content;

    if (examOnly) {
        return { icon: FileSpreadsheet, surface: 'bg-violet-50', accent: 'text-violet-600' };
    }
    if (assignmentOnly) {
        return { icon: ClipboardList, surface: 'bg-amber-50', accent: 'text-amber-600' };
    }
    return { icon: m.has_content ? BookOpen : FileText, surface: 'bg-sky-50', accent: 'text-sky-600' };
}

export default function MaterialListCard({ courseId, material }: Props) {
    const dateLabel = formatDate(material.available_from ?? material.created_at);
    const { icon: Icon, surface, accent } = pickAccent(material);

    return (
        <motion.div variants={fadeUp}>
            <Link
                href={route('student.materials.show', { course: courseId, material: material.id })}
                className="group flex items-start gap-4 rounded-2xl border border-slate-200 bg-white p-5 transition-colors hover:border-sky-300"
            >
                <div className={`flex h-10 w-10 shrink-0 items-center justify-center rounded-xl ${surface} ${accent}`}>
                    <Icon className="h-5 w-5" />
                </div>

                <div className="min-w-0 flex-1">
                    {material.topic && (
                        <div className="text-xs font-medium uppercase tracking-wide text-slate-400">
                            {material.topic}
                        </div>
                    )}
                    <h3 className="mt-0.5 truncate text-base font-semibold tracking-tight text-slate-900">
                        {material.title}
                    </h3>
                    {material.description && (
                        <p className="mt-1 line-clamp-2 text-sm text-slate-500">{material.description}</p>
                    )}

                    <div className="mt-3 flex flex-wrap items-center gap-1.5 text-xs">
                        {material.has_content && (
                            <Pill icon={BookOpen} tone="slate">
                                Bacaan
                            </Pill>
                        )}
                        {material.has_files && (
                            <Pill icon={Paperclip} tone="slate">
                                Lampiran
                            </Pill>
                        )}
                        {material.has_link && (
                            <Pill icon={LinkIcon} tone="slate">
                                Tautan
                            </Pill>
                        )}
                        {material.assignment_count > 0 && (
                            <Pill icon={ClipboardList} tone="amber">
                                {material.assignment_count} Tugas
                            </Pill>
                        )}
                        {material.exam_count > 0 && (
                            <Pill icon={FileSpreadsheet} tone="violet">
                                {material.exam_count} Ujian
                            </Pill>
                        )}
                        {dateLabel && <span className="ml-auto text-slate-400">{dateLabel}</span>}
                    </div>
                </div>

                <span className="mt-1 flex h-8 w-8 shrink-0 items-center justify-center rounded-lg text-slate-300 transition-colors group-hover:bg-slate-100 group-hover:text-slate-600">
                    <ChevronRight className="h-4 w-4 transition-transform group-hover:translate-x-0.5" />
                </span>
            </Link>
        </motion.div>
    );
}

const TONE: Record<string, string> = {
    slate: 'bg-slate-100 text-slate-600',
    amber: 'bg-amber-50 text-amber-700',
    violet: 'bg-violet-50 text-violet-700',
};

function Pill({ icon: Icon, tone, children }: { icon: LucideIcon; tone: keyof typeof TONE; children: React.ReactNode }) {
    return (
        <span className={`inline-flex items-center gap-1 rounded-md px-2 py-0.5 font-medium ${TONE[tone]}`}>
            <Icon className="h-3 w-3" />
            {children}
        </span>
    );
}
