import AdminLayout from '../../components/AdminLayout';

// 管理者の画面のメニュー。中身はこれから作る
const MENU = [
    { label: '製品の一覧', requirement: 'R-12' },
    { label: '指示の登録・削除', requirement: 'R-03' },
    { label: '社員の登録', requirement: 'R-07' },
    { label: '会社カレンダー（休みの登録）', requirement: 'R-13' },
];

/** 管理者の画面の入口（R-06） */
export default function Home() {
    return (
        <AdminLayout title="管理者の画面">
            <p className="mb-4 text-gray-700">10分操作がないと、管理者の操作は自動で終わります。</p>
            <ul className="grid gap-3 sm:grid-cols-2">
                {MENU.map((item) => (
                    <li key={item.label} className="rounded-lg border border-gray-200 bg-white p-4">
                        <p className="font-semibold text-gray-900">{item.label}</p>
                        <p className="mt-1 text-sm text-gray-500">準備中（{item.requirement}）</p>
                    </li>
                ))}
            </ul>
        </AdminLayout>
    );
}
