import { motion } from 'framer-motion';
import {
    Download,
    FileArchive,
    FileAudio,
    FileImage,
    FileSpreadsheet,
    FileText,
    FileVideo,
    File as FileIcon,
    LucideIcon,
} from 'lucide-react';

export interface MaterialFile {
    id: string;
    name: string;
    file_name: string;
    mime_type: string | null;
    size: number;
    extension: string;
    url: string;
}

interface Props {
    file: MaterialFile;
}

const ICON_BY_EXT: Record<string, LucideIcon> = {
    pdf: FileText,
    doc: FileText,
    docx: FileText,
    txt: FileText,
    rtf: FileText,
    xls: FileSpreadsheet,
    xlsx: FileSpreadsheet,
    csv: FileSpreadsheet,
    ppt: FileText,
    pptx: FileText,
    png: FileImage,
    jpg: FileImage,
    jpeg: FileImage,
    gif: FileImage,
    webp: FileImage,
    svg: FileImage,
    mp3: FileAudio,
    wav: FileAudio,
    ogg: FileAudio,
    mp4: FileVideo,
    mov: FileVideo,
    webm: FileVideo,
    zip: FileArchive,
    rar: FileArchive,
    '7z': FileArchive,
    tar: FileArchive,
    gz: FileArchive,
};

function pickIcon(ext: string): LucideIcon {
    return ICON_BY_EXT[ext.toLowerCase()] ?? FileIcon;
}

function formatBytes(bytes: number): string {
    if (!bytes) return '0 B';
    const units = ['B', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(1024));
    const size = bytes / 1024 ** i;
    return `${size.toFixed(size >= 10 || i === 0 ? 0 : 1)} ${units[i]}`;
}

export default function FileCard({ file }: Props) {
    const Icon = pickIcon(file.extension);

    return (
        <motion.a
            href={file.url}
            target="_blank"
            rel="noopener noreferrer"
            download={file.file_name}
            whileHover={{ y: -2 }}
            transition={{ duration: 0.2, ease: [0.22, 1, 0.36, 1] }}
            className="group flex items-center gap-3 rounded-2xl border border-slate-200 bg-white p-4 transition-colors hover:border-sky-300"
        >
            <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-sky-50 text-sky-600">
                <Icon className="h-5 w-5" />
            </div>
            <div className="min-w-0 flex-1">
                <div className="truncate text-sm font-medium text-slate-900">{file.name}</div>
                <div className="mt-0.5 flex items-center gap-2 text-xs text-slate-500">
                    <span className="uppercase">{file.extension || 'file'}</span>
                    <span aria-hidden>•</span>
                    <span>{formatBytes(file.size)}</span>
                </div>
            </div>
            <span className="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg text-slate-400 transition-colors group-hover:bg-sky-50 group-hover:text-sky-600">
                <Download className="h-4 w-4" />
            </span>
        </motion.a>
    );
}
