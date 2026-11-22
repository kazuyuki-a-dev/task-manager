import { Link } from '@inertiajs/react';
import AdminLayout from '../../components/AdminLayout';
import { formatDateTime } from '../../lib/date';

type Backup = {
    fileName: string;
    /** 'YYYY-MM-DD HH:mm'。バックアップをとった日時 */
    takenAt: string;
    /** 何時間前か */
    hoursAgo: number;
    /** 決めた時間（初期値25時間）より前で、とれていないと知らせるか */
    isStale: boolean;
};

// 管理者の画面のメニュー。href がないものは、これから作る
const MENU: { label: string; requirement: string; href?: string }[] = [
    { label: '取引会社', requirement: 'R-14', href: '/admin/companies' },
    { label: '製品の一覧', requirement: 'R-12', href: '/admin/products' },
    { label: '指示の登録', requirement: 'R-03（指示を消すのは、ボードの小さな画面から）', href: '/admin/orders/create' },
    { label: '社員', requirement: 'R-07', href: '/admin/members' },
    { label: '会社カレンダー（休みと行事は、日付を押して足す）', requirement: 'R-13', href: '/calendar' },
];

/** 「6時間前」「2日前」 */
function agoText(hours: number): string {
    if (hours < 1) return '1時間以内';
    if (hours < 24) return `${hours}時間前`;
    return `${Math.floor(hours / 24)}日前`;
}

/**
 * 最後のバックアップ（R-09・D-046）。とれていれば緑、1日以上とれていなければ赤で知らせる。
 * 失敗した理由は画面には出さない（storage/logs/laravel.log を見る）
 */
function BackupStatus({ backup, stopped }: { backup: Backup | null; stopped: boolean }) {
    // お試し用のデモでは、バックアップを止めている（D-048）
    if (stopped) {
        return (
            <section className="mb-6 rounded-lg border-2 border-gray-300 bg-gray-100 px-4 py-3">
                <p className="font-bold text-gray-800">バックアップ：お試し用のデモでは止めています</p>
                <p className="mt-1 text-sm text-gray-700">デモのデータは、しばらくすると元に戻るためです。</p>
            </section>
        );
    }

    if (backup === null || backup.isStale) {
        return (
            <section role="alert" className="mb-6 rounded-lg border-2 border-red-400 bg-red-50 px-4 py-3">
                <p className="font-bold text-red-800">⚠ {backup === null ? 'まだバックアップがありません' : '1日以上、バックアップがとれていません'}</p>
                {backup && (
                    <p className="mt-1 text-red-900">
                        最後のバックアップ：{formatDateTime(backup.takenAt)}（{agoText(backup.hoursAgo)}）
                    </p>
                )}
                <p className="mt-1 text-sm text-red-900">
                    「バックアップと復元の手順書」の A-2 で手でとってから、担当に知らせてください。うまくいかない理由は storage/logs/laravel.log に書かれています。
                </p>
            </section>
        );
    }

    return (
        <section className="mb-6 rounded-lg border-2 border-green-300 bg-green-50 px-4 py-3">
            <p className="font-bold text-green-800">
                最後のバックアップ：{formatDateTime(backup.takenAt)}（{agoText(backup.hoursAgo)}）
            </p>
            <p className="mt-1 text-sm text-green-900">{backup.fileName}</p>
        </section>
    );
}

/** 管理者の画面の入口（R-06） */
export default function Home({ backup, backupStopped }: { backup: Backup | null; backupStopped: boolean }) {
    return (
        <AdminLayout title="管理者の画面">
            <BackupStatus backup={backup} stopped={backupStopped} />
            <p className="mb-4 text-gray-700">10分操作がないと、管理者の操作は自動で終わります。</p>
            <ul className="grid gap-3 sm:grid-cols-2">
                {MENU.map((item) => (
                    <li key={item.label}>
                        {item.href ? (
                            <Link href={item.href} className="block rounded-lg border border-gray-200 bg-white p-4 hover:border-blue-400 hover:shadow">
                                <p className="font-semibold text-blue-800">{item.label}</p>
                                <p className="mt-1 text-sm text-gray-500">{item.requirement}</p>
                            </Link>
                        ) : (
                            <div className="rounded-lg border border-gray-200 bg-white p-4">
                                <p className="font-semibold text-gray-900">{item.label}</p>
                                <p className="mt-1 text-sm text-gray-500">準備中（{item.requirement}）</p>
                            </div>
                        )}
                    </li>
                ))}
            </ul>
        </AdminLayout>
    );
}
