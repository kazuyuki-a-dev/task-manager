// サーバー（BoardController）から受けとるデータの形

export type OrderStatus = 'not_started' | 'in_progress' | 'done';

/** 'overdue' は遅れ（納期の翌日から）、'soon' は期限間近（納期の2日前から当日まで） */
export type Deadline = 'overdue' | 'soon';

/** 社員 */
export type Member = {
    id: number;
    name: string;
};

/** 取引会社 */
export type Company = {
    id: number;
    name: string;
};

/** 製品 */
export type Product = {
    id: number;
    /** 取引会社の名前 */
    companyName: string;
    /** 品番。登録されていなければ null */
    code: string | null;
    name: string;
};

export type Order = {
    id: number;
    productId: number;
    /** 取引会社の名前 */
    companyName: string;
    /** 品番。登録されていなければ null */
    productCode: string | null;
    productName: string;
    quantity: number;
    /** 'YYYY-MM-DD'。決まっていなければ null */
    materialReadyDate: string | null;
    /** 'YYYY-MM-DD' */
    dueDate: string;
    /** 納期まであと何日か。今日が納期なら 0、1日過ぎたら -1 */
    daysLeft: number;
    /** 遅れ・期限間近のどちらでもなければ null */
    deadline: Deadline | null;
    /** 担当（足した順）。まだだれもいなければ空 */
    assignees: Member[];
    status: OrderStatus;
    isDummy: boolean;
    /** 作業を始められない理由（担当が未割り当て）。始められるときは空 */
    startBlockers: string[];
    /** 最後に直された日時。ほかの端末の直しを上書きしないために、直すときに一緒に送る */
    version: string;
    /** 変更の記録。新しい順 */
    logs: OrderLogEntry[];
};

/** 変更の記録の1つ（R-08） */
export type OrderLogEntry = {
    id: number;
    /** created / status / product / quantity / material_ready_date / due_date / assignee_added / assignee_removed */
    action: string;
    /** 「状態」「納期」「担当を足した」など */
    label: string;
    oldValue: string | null;
    newValue: string | null;
    /** 'YYYY-MM-DD HH:mm' */
    at: string;
};

export type BoardColumn = {
    status: OrderStatus;
    label: string;
    orders: Order[];
};

/** サーバーから返ってくる、直せなかったときの理由 */
export type PageErrors = {
    status?: string;
    user_id?: string;
    version?: string;
};

/** すべての画面で受けとるデータ（HandleInertiaRequests の share） */
export type SharedProps = {
    /** パスワードを入れた管理者。入っていなければ null */
    admin: Member | null;
    /** 画面の上に一度だけ出すお知らせ */
    notice: string | null;
};
