// サーバー（BoardController）から受けとるデータの形

export type OrderStatus = 'not_started' | 'in_progress' | 'done';

/** 'overdue' は遅れ（納期の翌日から）、'soon' は期限間近（納期の2日前から当日まで） */
export type Deadline = 'overdue' | 'soon';

export type Order = {
    id: number;
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
    /** 担当の名前（足した順）。まだだれもいなければ空 */
    assignees: string[];
    status: OrderStatus;
    isDummy: boolean;
};

export type BoardColumn = {
    status: OrderStatus;
    label: string;
    orders: Order[];
};
