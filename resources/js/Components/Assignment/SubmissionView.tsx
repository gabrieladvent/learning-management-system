import { motion } from 'framer-motion';
import { Lock, Pencil } from 'lucide-react';
import { FileCard } from '@/Components';
import { fadeUp, staggerContainer } from '@/lib/motion';
import type { AssignmentSubmission } from './assignment.type';

interface Props {
    submission: AssignmentSubmission;
    canEdit: boolean;
    /** Alasan tidak bisa edit (mis. "Sudah dinilai", "Sudah lewat deadline"). Ditampilkan kalau !canEdit. */
    lockReason?: string;
    onEdit: () => void;
}

export default function SubmissionView({ submission, canEdit, lockReason, onEdit }: Props) {
    const hasContent = !!submission.content?.trim();
    const hasFiles = submission.files.length > 0;

    return (
        <div className="space-y-5">
            <div className="flex items-start justify-between gap-3 border-b border-slate-100 pb-4">
                <div>
                    <div className="text-xs font-medium uppercase tracking-wide text-slate-400">
                        Submission Kamu
                    </div>
                    <div className="mt-0.5 text-sm text-slate-600">
                        {submission.submitted_at
                            ? `Dikumpulkan ${new Date(submission.submitted_at).toLocaleString('id-ID', {
                                  day: 'numeric',
                                  month: 'long',
                                  year: 'numeric',
                                  hour: '2-digit',
                                  minute: '2-digit',
                              })}`
                            : 'Belum dikumpulkan'}
                    </div>
                </div>

                {canEdit ? (
                    <motion.button
                        type="button"
                        onClick={onEdit}
                        whileTap={{ scale: 0.97 }}
                        className="inline-flex shrink-0 items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-medium text-slate-700 transition hover:border-amber-300 hover:text-amber-700"
                    >
                        <Pencil className="h-3.5 w-3.5" />
                        Edit
                    </motion.button>
                ) : lockReason ? (
                    <span className="inline-flex shrink-0 items-center gap-1.5 rounded-lg bg-slate-100 px-3 py-1.5 text-xs font-medium text-slate-500">
                        <Lock className="h-3.5 w-3.5" />
                        {lockReason}
                    </span>
                ) : null}
            </div>

            {hasContent && (
                <div>
                    <div className="mb-1.5 text-xs font-semibold uppercase tracking-wide text-slate-500">
                        Jawaban
                    </div>
                    <div className="whitespace-pre-wrap rounded-xl border border-slate-100 bg-slate-50 p-4 text-sm leading-6 text-slate-800">
                        {submission.content}
                    </div>
                </div>
            )}

            {hasFiles && (
                <div>
                    <div className="mb-1.5 text-xs font-semibold uppercase tracking-wide text-slate-500">
                        Lampiran ({submission.files.length})
                    </div>
                    <motion.div
                        initial="hidden"
                        animate="visible"
                        variants={staggerContainer}
                        className="grid grid-cols-1 gap-3 sm:grid-cols-2"
                    >
                        {submission.files.map((file) => (
                            <motion.div key={file.id} variants={fadeUp}>
                                <FileCard file={file} />
                            </motion.div>
                        ))}
                    </motion.div>
                </div>
            )}

            {!hasContent && !hasFiles && (
                <p className="text-sm text-slate-500">
                    Submission tidak berisi jawaban tertulis maupun lampiran.
                </p>
            )}
        </div>
    );
}
