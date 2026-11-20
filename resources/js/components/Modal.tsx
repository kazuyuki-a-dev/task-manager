import { useEffect, useId, useRef } from 'react';

type Props = {
    title: React.ReactNode;
    /** 見出しの下に小さく出す文字 */
    subtitle?: React.ReactNode;
    onClose: () => void;
    children: React.ReactNode;
    /** 'sm' は小さめ（ミニ画面の上に重ねて出すときなど） */
    size?: 'sm' | 'md';
};

/**
 * 管理者の画面の小さな画面。ブラウザの <dialog> を使う
 * （うしろを押せない・Esc や外側を押すと閉じる、をブラウザがしてくれる）
 */
export default function Modal({ title, subtitle, onClose, children, size = 'md' }: Props) {
    const dialogRef = useRef<HTMLDialogElement>(null);
    // ミニ画面を重ねても見出しを取りちがえないように、見出しの名前は毎回ちがうものにする
    const titleId = useId();

    useEffect(() => {
        const dialog = dialogRef.current;
        dialog?.showModal();
        return () => dialog?.close();
    }, []);

    return (
        <dialog
            ref={dialogRef}
            onClose={onClose}
            onClick={(event) => event.target === dialogRef.current && onClose()}
            aria-labelledby={titleId}
            className={`m-auto w-[calc(100%-2rem)] ${size === 'sm' ? 'max-w-sm' : 'max-w-lg'} rounded-xl p-0 shadow-xl backdrop:bg-black/40`}
        >
            <div className="max-h-[85vh] overflow-y-auto">
                <header className="sticky top-0 flex items-start justify-between gap-2 border-b border-gray-200 bg-white px-4 py-3">
                    <div>
                        <h2 id={titleId} className="text-lg font-semibold text-gray-900">
                            {title}
                        </h2>
                        {subtitle && <p className="text-sm text-gray-500">{subtitle}</p>}
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
                <div className="space-y-6 px-4 py-4">{children}</div>
            </div>
        </dialog>
    );
}
