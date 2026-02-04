/**
 * Help Category interface.
 * Matches the HelpCategoryResource from the backend.
 */
export interface HelpCategory {
    id: number;
    name: string;
    slug: string;
    description: string | null;
    icon: string | null;
    sort_order: number;
    is_active: boolean;
    articles_count?: number;
    active_articles_count?: number;
    created_at: string | null;
    updated_at: string | null;
}

/**
 * Help Article interface.
 * Matches the HelpArticleResource from the backend.
 */
export interface HelpArticle {
    id: number;
    help_category_id: number;
    title: string;
    slug: string;
    excerpt: string | null;
    content: string;
    related_article_ids: number[] | null;
    sort_order: number;
    is_active: boolean;
    is_featured: boolean;
    view_count: number;
    category?: HelpCategory;
    created_at: string | null;
    updated_at: string | null;
}

/**
 * Pagination metadata interface for help articles.
 */
export interface HelpPaginationMeta {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
}

/**
 * Paginated help articles response.
 */
export interface PaginatedHelpArticles {
    data: HelpArticle[];
    meta: HelpPaginationMeta;
    links: {
        first: string | null;
        last: string | null;
        prev: string | null;
        next: string | null;
    };
}

/**
 * Help Center Index page props.
 */
export interface HelpIndexProps {
    categories: HelpCategory[];
    featuredArticles: HelpArticle[];
}

/**
 * Help Center Category page props.
 */
export interface HelpCategoryProps {
    category: HelpCategory;
    articles: HelpArticle[];
    categories: HelpCategory[];
}

/**
 * Help Center Article page props.
 */
export interface HelpArticleProps {
    article: HelpArticle;
    category: HelpCategory;
    relatedArticles: HelpArticle[];
    previousArticle: HelpArticle | null;
    nextArticle: HelpArticle | null;
    categories: HelpCategory[];
}

/**
 * Help Center Search page props.
 */
export interface HelpSearchProps {
    query: string;
    articles: HelpArticle[];
    categories: HelpCategory[];
}

/**
 * Help Admin page filters.
 */
export interface HelpAdminFilters {
    category_id: string | null;
    search: string | null;
}

/**
 * Help Admin page props.
 */
export interface HelpAdminProps {
    categories: HelpCategory[];
    articles: PaginatedHelpArticles;
    filters: HelpAdminFilters;
}

/**
 * Form data for creating/editing a help category.
 */
export interface HelpCategoryFormData {
    name: string;
    slug: string;
    description: string;
    icon: string;
    sort_order: number;
    is_active: boolean;
}

/**
 * Form data for creating/editing a help article.
 */
export interface HelpArticleFormData {
    help_category_id: number | '';
    title: string;
    slug: string;
    excerpt: string;
    content: string;
    related_article_ids: number[];
    sort_order: number;
    is_active: boolean;
    is_featured: boolean;
}
