// サーバー（BoardController）から受けとるデータの形

export type OrderStatus = 'not_started' | 'in_progress' | 'done';

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
