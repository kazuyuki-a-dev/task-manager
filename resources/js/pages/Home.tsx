type Props = {
    appName: string;
};

// 仮のトップページ。進み具合のボード（R-01）ができたら置きかえる
export default function Home({ appName }: Props) {
    return (
        <main className="flex min-h-screen items-center justify-center bg-gray-50">
            <div className="text-center">
                <h1 className="text-2xl font-semibold text-gray-900">{appName}</h1>
                <p className="mt-2 text-gray-600">準備中です</p>
            </div>
        </main>
    );
}
