import { router, useForm } from '@inertiajs/react';
import { useEffect, useMemo, useRef, useState } from 'react';
import { formatDate } from '../lib/date';
import type { Member, Order, OrderStatus, PageErrors, Product } from '../types';

type Props = {
    order: Order;
    members: Member[];
    products: Product[];
    errors: PageErrors;
    onClose: () => void;
};

// 押したあとも、うしろのボードの同じ位置のままにする
const KEEP_SCROLL = { preserveScroll: true, preserveState: true } as const;

/**
 * カードを押すと開く小さな画面（D-028）。
 * 内容（製品・数量・材料集結日・納期）・状態・担当を、だれでも直せる（D-027）
 */
export default function OrderModal({ order, members, products, errors, onClose }: Props) {
    const dialogRef = useRef<HTMLDialogElement>(null);

    // 開いたら、ブラウザの <dialog> の「うしろを押せない形」で出す。Esc でも閉じる
    useEffect(() => {
        const dialog = dialogRef.current;
        dialog?.showModal();
        return () => dialog?.close();
    }, []);

    const pageError = errors.status ?? errors.user_id ?? errors.version;

    return (
        <dialog
            ref={dialogRef}
            onClose={onClose}
            // 外側（暗いところ）を押しても閉じる
            onClick={(event) => event.target === dialogRef.current && onClose()}
            aria-labelledby="order-modal-title"
            className="m-auto w-[calc(100%-2rem)] max-w-xl rounded-xl p-0 shadow-xl backdrop:bg-black/40"
        >
            <div className="max-h-[85vh] overflow-y-auto">
                <header className="sticky top-0 flex items-start justify-between gap-2 border-b border-gray-200 bg-white px-4 py-3">
                    <div>
                        <p className="text-sm text-gray-500">
                            {order.companyName}
                            {order.productCode && `・${order.productCode}`}
                        </p>
                        <h2 id="order-modal-title" className="text-lg font-semibold text-gray-900">
                            {order.productName}
                        </h2>
                    </div>
                    <button
                        type="button"
                        onClick={onClose}
                        aria-label="閉じる"
                        className="flex h-11 w-11 shrink-0 items-center justify-center rounded-full text-2xl text-gray-500 hover:bg-gray-100"
                    >
                        ×
                    </button>
                </header>

                <div className="space-y-6 px-4 py-4">
                    {pageError && (
                        <p role="alert" className="rounded-lg border border-red-300 bg-red-50 px-3 py-2 font-medium text-red-800">
                            {pageError}
                        </p>
                    )}
                    <StatusSection order={order} onClose={onClose} />
                    <AssigneeSection order={order} members={members} />
                    <DetailForm order={order} products={products} />
                </div>
            </div>
        </dialog>
    );
}

function SectionTitle({ children }: { children: React.ReactNode }) {
    return <h3 className="mb-2 text-sm font-semibold text-gray-500">{children}</h3>;
}

type StatusAction = {
    next: OrderStatus;
    label: string;
    style: string;
    /** うまく変わったら、小さな画面を閉じる */
    closeAfter?: boolean;
};

// 左が戻すボタン、右がすすめるボタン。
// 完了 → 作業中に戻すボタンは、完了した指示が出る履歴の画面に置く（D-024）
const STATUS_ACTIONS: Record<OrderStatus, StatusAction[]> = {
    // 作業を始めたら、そのまま小さな画面を閉じる
    not_started: [{ next: 'in_progress', label: '作業を始める', style: 'bg-blue-600 text-white hover:bg-blue-700', closeAfter: true }],
    in_progress: [
        // まちがえて始めたときに戻す（D-025）。すすめるボタンと見分けやすいように、白地の枠線にする
        { next: 'not_started', label: '未着手に戻す', style: 'border border-gray-300 bg-white text-gray-700 hover:bg-gray-50' },
        { next: 'done', label: '完了にする', style: 'bg-green-700 text-white hover:bg-green-800' },
    ],
    done: [],
};

const STATUS_LABELS: Record<OrderStatus, string> = {
    not_started: '未着手',
    in_progress: '作業中',
    done: '完了',
};

/** 状態を変える（R-04） */
function StatusSection({ order, onClose }: { order: Order; onClose: () => void }) {
    const [processing, setProcessing] = useState(false);

    const change = (action: StatusAction) => {
        router.patch(`/orders/${order.id}/status`, { status: action.next }, {
            ...KEEP_SCROLL,
            onStart: () => setProcessing(true),
            onFinish: () => setProcessing(false),
            // 変えられなかったとき（ほかの端末で先に変わっていたなど）は、理由が見えるように閉じない
            onSuccess: () => action.closeAfter && onClose(),
        });
    };

    // 未着手から始めるときだけ、担当がいるか見る（D-030）
    const blockedFor = (action: StatusAction) =>
        order.status === 'not_started' && action.next === 'in_progress' && order.startBlockers.length > 0;

    return (
        <section>
            <SectionTitle>状態</SectionTitle>
            <div className="flex flex-wrap items-center gap-2">
                <span className="mr-auto text-base font-semibold text-gray-900">{STATUS_LABELS[order.status]}</span>
                {STATUS_ACTIONS[order.status].map((action) => (
                    <button
                        key={action.next}
                        type="button"
                        onClick={() => change(action)}
                        disabled={processing || blockedFor(action)}
                        className={`rounded-lg px-4 py-2.5 text-sm font-bold disabled:opacity-50 ${action.style}`}
                    >
                        {action.label}
                    </button>
                ))}
            </div>
            {order.status === 'not_started' && order.startBlockers.length > 0 && (
                <ul className="mt-2 space-y-0.5 text-right text-sm font-medium text-amber-800">
                    {order.startBlockers.map((reason) => (
                        <li key={reason}>{reason}</li>
                    ))}
                </ul>
            )}
        </section>
    );
}

/** 担当を足す・外す（R-11） */
function AssigneeSection({ order, members }: { order: Order; members: Member[] }) {
    const [processing, setProcessing] = useState(false);
    const options = { ...KEEP_SCROLL, onStart: () => setProcessing(true), onFinish: () => setProcessing(false) };

    // もう担当に入っている人は、プルダウンに出さない
    const assignedIds = new Set(order.assignees.map((member) => member.id));
    const candidates = members.filter((member) => !assignedIds.has(member.id));

    const add = (userId: string) => {
        if (userId === '') return;
        router.post(`/orders/${order.id}/assignees`, { user_id: Number(userId) }, options);
    };
    const remove = (member: Member) => {
        router.delete(`/orders/${order.id}/assignees/${member.id}`, options);
    };

    return (
        <section>
            <SectionTitle>担当</SectionTitle>
            {order.assignees.length === 0 ? (
                <p className="mb-2 text-red-700">未割り当て</p>
            ) : (
                <ul className="mb-2 flex flex-wrap gap-1.5">
                    {order.assignees.map((member) => (
                        <li key={member.id} className="flex items-center rounded-full bg-gray-100 pl-3 text-gray-900">
                            {member.name}
                            <button
                                type="button"
                                onClick={() => remove(member)}
                                disabled={processing}
                                aria-label={`${member.name}さんを担当から外す`}
                                className="ml-1 flex h-9 w-9 items-center justify-center rounded-full text-gray-500 hover:bg-gray-200 hover:text-gray-900 disabled:opacity-50"
                            >
                                ×
                            </button>
                        </li>
                    ))}
                </ul>
            )}
            {candidates.length > 0 && (
                <select
                    aria-label="担当を足す"
                    value=""
                    onChange={(event) => add(event.target.value)}
                    disabled={processing}
                    className="w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-gray-700 disabled:opacity-50"
                >
                    <option value="">＋ 担当を足す</option>
                    {candidates.map((member) => (
                        <option key={member.id} value={member.id}>
                            {member.name}
                        </option>
                    ))}
                </select>
            )}
        </section>
    );
}

/** 探すときに、大文字・小文字と前後の空白のちがいを気にしなくてよいようにする */
const normalize = (value: string) => value.trim().toLowerCase();

// 候補が多すぎると選びにくいので、出すのはここまで
const MAX_CANDIDATES = 30;

/** 製品を、取引会社・品番・品名の一部で探して選ぶ（R-12・D-019・D-033） */
function ProductPicker({ products, value, onChange, error }: {
    products: Product[];
    value: number;
    onChange: (productId: number) => void;
    error?: string;
}) {
    const [query, setQuery] = useState('');
    const selected = products.find((product) => product.id === value);

    const matches = useMemo(() => {
        const q = normalize(query);
        if (q === '') return [];
        return products
            .filter(
                (product) =>
                    normalize(product.name).includes(q) ||
                    normalize(product.code ?? '').includes(q) ||
                    normalize(product.companyName).includes(q),
            )
            .slice(0, MAX_CANDIDATES);
    }, [products, query]);

    return (
        <div>
            <p className="mb-1 text-gray-900">
                {selected ? (
                    <>
                        <span className="mr-1 text-gray-500">
                            {selected.companyName}
                            {selected.code && `・${selected.code}`}
                        </span>
                        {selected.name}
                    </>
                ) : (
                    '選ばれていません'
                )}
            </p>
            <input
                type="search"
                value={query}
                onChange={(event) => setQuery(event.target.value)}
                placeholder="取引会社・品番・品名の一部で探す"
                aria-label="製品を探す"
                className="w-full rounded-lg border border-gray-300 px-3 py-2.5"
            />
            {query !== '' && (
                <ul className="mt-1 max-h-48 overflow-y-auto rounded-lg border border-gray-200">
                    {matches.length === 0 ? (
                        <li className="px-3 py-2 text-gray-500">見つかりません</li>
                    ) : (
                        matches.map((product) => (
                            <li key={product.id}>
                                <button
                                    type="button"
                                    onClick={() => {
                                        onChange(product.id);
                                        setQuery('');
                                    }}
                                    className={`w-full px-3 py-2.5 text-left hover:bg-blue-50 ${product.id === value ? 'bg-blue-50 font-semibold' : ''}`}
                                >
                                    <span className="mr-2 text-gray-500">
                                        {product.companyName}
                                        {product.code && `・${product.code}`}
                                    </span>
                                    {product.name}
                                </button>
                            </li>
                        ))
                    )}
                </ul>
            )}
            {error && <p className="mt-1 text-sm text-red-700">{error}</p>}
        </div>
    );
}

/** 内容（製品・数量・材料集結日・納期）を直す（R-03 の編集） */
function DetailForm({ order, products }: { order: Order; products: Product[] }) {
    const form = useForm({
        product_id: order.productId,
        quantity: String(order.quantity),
        material_ready_date: order.materialReadyDate ?? '',
        due_date: order.dueDate,
    });

    const submit = (event: React.FormEvent) => {
        event.preventDefault();
        // 送るときの「最後に直された日時」は、この画面が今知っている新しいものにする
        form.transform((data) => ({
            ...data,
            material_ready_date: data.material_ready_date === '' ? null : data.material_ready_date,
            version: order.version,
        }));
        form.patch(`/orders/${order.id}`, {
            ...KEEP_SCROLL,
            // 保存できたら、今の値を「もとの値」にする（「内容を保存」をまた押せなくする）
            onSuccess: () => form.setDefaults(),
        });
    };

    const inputClass = 'w-full rounded-lg border border-gray-300 px-3 py-2.5';

    return (
        <form onSubmit={submit}>
            <SectionTitle>内容</SectionTitle>
            <div className="space-y-4">
                <div>
                    <p className="mb-1 font-medium text-gray-700">製品</p>
                    <ProductPicker
                        products={products}
                        value={form.data.product_id}
                        onChange={(productId) => form.setData('product_id', productId)}
                        error={form.errors.product_id}
                    />
                </div>
                <label className="block">
                    <span className="mb-1 block font-medium text-gray-700">数量</span>
                    <input
                        type="number"
                        inputMode="numeric"
                        min={1}
                        value={form.data.quantity}
                        onChange={(event) => form.setData('quantity', event.target.value)}
                        className={inputClass}
                    />
                    {form.errors.quantity && <span className="mt-1 block text-sm text-red-700">{form.errors.quantity}</span>}
                </label>
                <div className="grid gap-4 sm:grid-cols-2">
                    <label className="block">
                        <span className="mb-1 block font-medium text-gray-700">材料集結日</span>
                        <input
                            type="date"
                            value={form.data.material_ready_date}
                            onChange={(event) => form.setData('material_ready_date', event.target.value)}
                            className={inputClass}
                        />
                        <span className="mt-1 block text-sm text-gray-500">
                            {form.data.material_ready_date ? formatDate(form.data.material_ready_date) : '空なら「未定」'}
                        </span>
                        {form.errors.material_ready_date && (
                            <span className="mt-1 block text-sm text-red-700">{form.errors.material_ready_date}</span>
                        )}
                    </label>
                    <label className="block">
                        <span className="mb-1 block font-medium text-gray-700">納期</span>
                        <input
                            type="date"
                            required
                            value={form.data.due_date}
                            onChange={(event) => form.setData('due_date', event.target.value)}
                            className={inputClass}
                        />
                        {form.data.due_date && (
                            <span className="mt-1 block text-sm text-gray-500">{formatDate(form.data.due_date)}</span>
                        )}
                        {form.errors.due_date && <span className="mt-1 block text-sm text-red-700">{form.errors.due_date}</span>}
                    </label>
                </div>
            </div>
            <div className="mt-4 flex items-center justify-end gap-3">
                {form.recentlySuccessful && <span className="text-sm font-medium text-green-700">保存しました</span>}
                <button
                    type="submit"
                    // 何も変えていないときは押せない
                    disabled={form.processing || !form.isDirty}
                    className="rounded-lg bg-blue-600 px-5 py-2.5 text-sm font-bold text-white hover:bg-blue-700 disabled:opacity-50"
                >
                    内容を保存
                </button>
            </div>
        </form>
    );
}
