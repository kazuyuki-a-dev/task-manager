// サーバー（BoardController）から受けとるデータの形

export type OrderStatus = 'not_started' | 'in_progress' | 'done';

/** 'overdue' は遅れ（納期の翌日から）、'soon' は期限間近（納期の2日前から当日まで） */
export type Deadline = 'overdue' | 'soon';

/** 社員 */
export type Member = {
    id: number;
    name: string;
};

/** 製品 */
export type Product = {
    id: number;
    /** 品番。登録されていなければ null */
    code: string | null;
    name: string;
};

export type Order = {
    id: number;
    productId: number;
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
    /** 最後に直された日時。ほかの端末の直しを上書きしないために、直すときに一緒に送る */
    version: string;
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
