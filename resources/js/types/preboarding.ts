export interface PreboardingChecklistItem {
    id: number;
    type: 'document_upload' | 'form_field' | 'acknowledgment';
    type_label: string;
    name: string;
    description: string | null;
    is_required: boolean;
    status: 'pending' | 'submitted' | 'approved' | 'rejected';
    status_label: string;
    status_color: string;
    form_value: string | null;
    rejection_reason: string | null;
    submitted_at: string | null;
    reviewed_at: string | null;
    document_id: number | null;
    document?: {
        id: number;
        name: string;
        original_filename: string;
        mime_type: string;
        url: string | null;
    } | null;
    document_category?: string | null;
}

export interface PreboardingChecklist {
    id: number;
    status: 'pending' | 'in_progress' | 'completed' | 'overdue';
    status_label: string;
    status_color: string;
    deadline: string | null;
    completed_at: string | null;
    progress_percentage: number;
    candidate_name?: string;
    candidate_email?: string;
    position_title?: string;
    start_date?: string;
    total_items?: number;
    approved_items?: number;
    created_at?: string;
    employee_id?: number | null;
    items: PreboardingChecklistItem[];
}

export interface PreboardingTemplateItem {
    id: number;
    type: 'document_upload' | 'form_field' | 'acknowledgment';
    type_label: string;
    name: string;
    description: string | null;
    is_required: boolean;
    sort_order: number;
    document_category_id: number | null;
}

export interface PreboardingTemplate {
    id: number;
    name: string;
    description: string | null;
    is_default: boolean;
    is_active: boolean;
    items_count?: number;
    items?: PreboardingTemplateItem[];
    created_at?: string;
}

export interface StatusOption {
    value: string;
    label: string;
    color: string;
}
