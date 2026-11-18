export type ReviewField = {
    id: string;
    label: string;
    description: string | null;
    type: string;
    required: boolean;
    options: string[];
    max_rating: number;
};

export type ReviewData = {
    id: number;
    title: string;
    description: string | null;
    audience: string;
    audience_label: string;
    status: string;
    status_label: string;
    collect_respondent_name: boolean;
    fields: ReviewField[];
    responses_count: number;
    created_at: string | null;
    updated_at: string | null;
};
