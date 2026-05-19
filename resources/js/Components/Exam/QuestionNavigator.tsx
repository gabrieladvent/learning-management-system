import { CheckCircle2 } from 'lucide-react';
import type { ExamQuestionItem } from './exam.type';

interface Props {
    questions: ExamQuestionItem[];
    answers: Record<string, string | null>;
    currentIndex: number;
    onJump: (index: number) => void;
}

function isAnswered(value: string | null | undefined): boolean {
    if (value === null || value === undefined) return false;

    return value.trim().length > 0;
}

export default function QuestionNavigator({ questions, answers, currentIndex, onJump }: Props) {
    return (
        <div className="rounded-2xl border border-slate-200 bg-white p-4">
            <div className="mb-3 flex items-center justify-between">
                <h3 className="text-sm font-semibold tracking-tight text-slate-900">Navigasi Soal</h3>
                <span className="text-xs text-slate-500">
                    {Object.keys(answers).filter((id) => isAnswered(answers[id])).length} / {questions.length}
                </span>
            </div>
            <div className="grid grid-cols-5 gap-2 sm:grid-cols-8 lg:grid-cols-5">
                {questions.map((q, idx) => {
                    const active = idx === currentIndex;
                    const answered = isAnswered(answers[q.id]);

                    const base =
                        'relative flex h-9 w-9 items-center justify-center rounded-lg text-xs font-semibold transition-colors';
                    const tone = active
                        ? 'bg-violet-600 text-white shadow-sm'
                        : answered
                          ? 'bg-emerald-50 text-emerald-700 hover:bg-emerald-100'
                          : 'bg-slate-100 text-slate-600 hover:bg-slate-200';

                    return (
                        <button
                            key={q.id}
                            type="button"
                            onClick={() => onJump(idx)}
                            className={`${base} ${tone}`}
                            aria-label={`Soal ${idx + 1}${answered ? ' (sudah dijawab)' : ''}`}
                            aria-current={active ? 'true' : undefined}
                        >
                            {idx + 1}
                            {answered && !active && (
                                <CheckCircle2 className="absolute -right-1 -top-1 h-3 w-3 text-emerald-600" />
                            )}
                        </button>
                    );
                })}
            </div>
        </div>
    );
}
