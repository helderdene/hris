export interface ProficiencyLevel {
    id: number;
    level: number;
    name: string;
    description: string;
    behavioral_indicators: string[];
    created_at: string;
    updated_at: string;
}

export interface Competency {
    id: number;
    name: string;
    code: string;
    description: string | null;
    category: string | null;
    category_label: string | null;
    is_active: boolean;
    position_competencies_count?: number;
    created_at: string;
    updated_at: string;
}

export interface Position {
    id: number;
    title: string;
    code: string;
}

export interface PositionCompetency {
    id: number;
    position_id: number;
    competency_id: number;
    job_level: string;
    job_level_label: string;
    required_proficiency_level: number;
    is_mandatory: boolean;
    weight: string;
    notes: string | null;
    position?: Position;
    competency?: Competency;
    proficiency_level?: ProficiencyLevel | null;
    created_at: string;
    updated_at: string;
}

export interface CompetencyEvaluation {
    id: number;
    performance_cycle_participant_id: number;
    position_competency_id: number;
    self_rating: number | null;
    self_comments: string | null;
    manager_rating: number | null;
    manager_comments: string | null;
    final_rating: number | null;
    evidence: string[];
    evaluated_at: string | null;
    is_complete: boolean;
    rating_gap: number | null;
    proficiency_gap: number | null;
    position_competency?: PositionCompetency;
    participant?: {
        id: number;
        employee?: {
            id: number;
            full_name: string;
            employee_code: string | null;
        };
    };
    created_at: string;
    updated_at: string;
}

export interface CategoryOption {
    value: string;
    label: string;
    description: string;
}

export interface JobLevelOption {
    value: string;
    label: string;
}

export interface CompetencyFormData {
    name: string;
    code: string;
    description: string;
    category: string;
    is_active: boolean;
}

export interface PositionCompetencyFormData {
    position_id: number;
    competency_id: number;
    job_level: string;
    required_proficiency_level: number;
    is_mandatory: boolean;
    weight: number;
    notes: string;
}

export interface CompetencyEvaluationFormData {
    self_rating?: number;
    self_comments?: string;
    manager_rating?: number;
    manager_comments?: string;
    final_rating?: number;
    evidence?: string[];
}
