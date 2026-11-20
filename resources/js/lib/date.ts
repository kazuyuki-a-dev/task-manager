const WEEKDAYS = ['日', '月', '火', '水', '木', '金', '土'];

/**
 * 'YYYY-MM-DD' を「11/22(日)」の形にする。
 * new Date('YYYY-MM-DD') は世界標準時として読まれ、日付がずれることがあるので、数字に分けて作る
 */
export function formatDate(value: string): string {
    const [year, month, day] = value.split('-').map(Number);
    const date = new Date(year, month - 1, day);
    return `${month}/${day}(${WEEKDAYS[date.getDay()]})`;
}

/** 「3日遅れ」「今日まで」「あと2日」 */
export function deadlineText(daysLeft: number): string {
    if (daysLeft < 0) return `${-daysLeft}日遅れ`;
    if (daysLeft === 0) return '今日まで';
    return `あと${daysLeft}日`;
}

/** 'YYYY-MM-DD HH:mm' を「11/20(金) 9:15」の形にする */
export function formatDateTime(value: string): string {
    const [date, time] = value.split(' ');
    const [hour, minute] = time.split(':');
    return `${formatDate(date)} ${Number(hour)}:${minute}`;
}
