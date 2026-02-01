/**
 * Document Category interface.
 * Matches the DocumentCategoryResource from the backend.
 */
export interface DocumentCategory {
    id: number;
    name: string;
    description: string | null;
    is_predefined: boolean;
    created_at: string | null;
    updated_at: string | null;
}

/**
 * Document Version interface.
 * Matches the DocumentVersionResource from the backend.
 */
export interface DocumentVersion {
    id: number;
    version_number: number;
    file_size: number;
    file_size_formatted: string;
    mime_type: string;
    uploaded_at: string | null;
    uploaded_by: number | null;
    uploaded_by_name: string | null;
    version_notes: string | null;
    created_at: string | null;
}

/**
 * Document interface.
 * Matches the DocumentResource from the backend.
 */
export interface Document {
    id: number;
    name: string;
    category: DocumentCategory | null;
    current_version: number;
    file_type: string;
    mime_type: string;
    file_size: number;
    file_size_formatted: string;
    original_filename: string;
    is_company_document: boolean;
    uploaded_at: string | null;
    uploaded_by: number | null;
    uploaded_by_name: string | null;
    versions: DocumentVersion[];
    created_at: string | null;
    updated_at: string | null;
}

/**
 * Pagination metadata interface.
 */
export interface PaginationMeta {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
}

/**
 * API response interface for document list endpoints.
 */
export interface DocumentApiResponse {
    data: Document[];
    meta: PaginationMeta;
}

/**
 * API response interface for single document endpoint.
 */
export interface DocumentShowResponse {
    data: Document;
}

/**
 * API response interface for document category list endpoint.
 */
export interface DocumentCategoryApiResponse {
    data: DocumentCategory[];
}

/**
 * Form data interface for uploading a document.
 */
export interface DocumentUploadFormData {
    file: File | null;
    name: string;
    document_category_id: number | '';
    version_notes: string;
}

/**
 * Validation errors interface for the document upload form.
 */
export interface DocumentUploadFormErrors {
    file?: string;
    name?: string;
    document_category_id?: string;
    version_notes?: string;
    general?: string;
}

/**
 * Props interface for DocumentsTab component.
 */
export interface DocumentsTabProps {
    employeeId: number;
}

/**
 * Props interface for DocumentUploadModal component.
 */
export interface DocumentUploadModalProps {
    employeeId: number;
    categories: DocumentCategory[];
}

/**
 * Props interface for DocumentTableRow component.
 */
export interface DocumentTableRowProps {
    document: Document;
    canManage: boolean;
}

/**
 * Allowed MIME types for document uploads.
 */
export const ALLOWED_MIME_TYPES = [
    'application/pdf',
    'application/msword',
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'image/jpeg',
    'image/png',
    'application/vnd.ms-excel',
    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
] as const;

/**
 * File type labels for display.
 */
export const FILE_TYPE_LABELS: Record<string, string> = {
    'application/pdf': 'PDF',
    'application/msword': 'DOC',
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document':
        'DOCX',
    'image/jpeg': 'JPG',
    'image/png': 'PNG',
    'application/vnd.ms-excel': 'XLS',
    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet': 'XLSX',
};

/**
 * File extension to MIME type mapping.
 */
export const EXTENSION_MIME_MAP: Record<string, string> = {
    pdf: 'application/pdf',
    doc: 'application/msword',
    docx: 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    jpg: 'image/jpeg',
    jpeg: 'image/jpeg',
    png: 'image/png',
    xls: 'application/vnd.ms-excel',
    xlsx: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
};

/**
 * MIME types that support in-browser preview.
 */
export const PREVIEWABLE_MIME_TYPES = [
    'application/pdf',
    'image/jpeg',
    'image/png',
] as const;

/**
 * Maximum file size in bytes (10MB).
 */
export const MAX_FILE_SIZE = 10 * 1024 * 1024;

/**
 * Maximum file size formatted for display.
 */
export const MAX_FILE_SIZE_LABEL = '10 MB';

/**
 * Check if a MIME type is previewable.
 */
export function isPreviewable(mimeType: string): boolean {
    return (PREVIEWABLE_MIME_TYPES as readonly string[]).includes(mimeType);
}

/**
 * Check if a MIME type is allowed.
 */
export function isAllowedMimeType(mimeType: string): boolean {
    return (ALLOWED_MIME_TYPES as readonly string[]).includes(mimeType);
}

/**
 * Get file type label from MIME type.
 */
export function getFileTypeLabel(mimeType: string): string {
    return FILE_TYPE_LABELS[mimeType] || 'FILE';
}

/**
 * Format file size to human-readable format.
 */
export function formatFileSize(bytes: number): string {
    if (bytes === 0) return '0 B';

    const units = ['B', 'KB', 'MB', 'GB'];
    const pow = Math.floor(Math.log(bytes) / Math.log(1024));
    const safePow = Math.min(pow, units.length - 1);

    return `${(bytes / Math.pow(1024, safePow)).toFixed(2)} ${units[safePow]}`;
}
