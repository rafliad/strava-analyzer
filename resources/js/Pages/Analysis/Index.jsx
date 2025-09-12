import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head } from '@inertiajs/react';
import axios from 'axios';
import { useState } from 'react';
import ReactMarkdown from 'react-markdown';

// Fungsi helper untuk mendapatkan tanggal dalam format YYYY-MM-DD
const getFormattedDate = (date) => {
    return date.toISOString().split('T')[0];
};

export default function Index({ auth }) {
    const [isLoading, setIsLoading] = useState(false);
    const [analysisResult, setAnalysisResult] = useState('');
    const [error, setError] = useState('');

    // State untuk rentang tanggal, defaultnya adalah bulan ini
    const [startDate, setStartDate] = useState(() => {
        const today = new Date();
        return getFormattedDate(new Date(today.getFullYear(), today.getMonth(), 1));
    });
    const [endDate, setEndDate] = useState(getFormattedDate(new Date()));

    const handleAnalysisClick = async () => {
        setIsLoading(true);
        setError('');
        setAnalysisResult('');

        try {
            // Kirim rentang tanggal sebagai bagian dari request body
            const response = await axios.post(route('analysis.perform'), {
                startDate,
                endDate
            });
            setAnalysisResult(response.data.analysis);
        } catch (err) {
            const errorMessage = err.response?.data?.error || 'An unknown error occurred.';
            setError(errorMessage);
            console.error(err);
        } finally {
            setIsLoading(false);
        }
    };

    return (
        <AuthenticatedLayout
            user={auth.user}
            header={<h2 className="font-semibold text-xl text-gray-800 leading-tight">AI Performance Analysis</h2>}
        >
            <Head title="AI Analysis" />

            <div className="py-12">
                <div className="max-w-7xl mx-auto sm:px-6 lg:px-8">
                    <div className="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                        <div className="mb-4 text-center">
                            <h3 className="text-lg font-medium text-gray-900">Get Your Performance Review</h3>
                            <p className="mt-1 text-sm text-gray-600">
                                Select a date range and let our AI coach provide you with analysis and feedback.
                            </p>
                        </div>
                        
                        {/* Input untuk memilih rentang tanggal */}
                        <div className="flex justify-center items-center gap-4 mb-6">
                            <div>
                                <label htmlFor="startDate" className="block text-sm font-medium text-gray-700">Start Date</label>
                                <input
                                    type="date"
                                    id="startDate"
                                    value={startDate}
                                    onChange={(e) => setStartDate(e.target.value)}
                                    className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                                />
                            </div>
                            <div>
                                <label htmlFor="endDate" className="block text-sm font-medium text-gray-700">End Date</label>
                                <input
                                    type="date"
                                    id="endDate"
                                    value={endDate}
                                    onChange={(e) => setEndDate(e.target.value)}
                                    className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                                />
                            </div>
                        </div>

                        <div className="text-center">
                            <button
                                onClick={handleAnalysisClick}
                                disabled={isLoading}
                                className="px-6 py-3 bg-indigo-600 text-white font-semibold rounded-lg shadow-md hover:bg-indigo-800 disabled:bg-indigo-300 disabled:cursor-not-allowed transition-colors"
                            >
                                {isLoading ? 'Analyzing...' : 'Analyze Selected Range'}
                            </button>
                        </div>
                    </div>

                    {analysisResult && (
                        <div className="mt-6 bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                            <div className="prose prose-lg max-w-none">
                                <ReactMarkdown
                                    components={{
                                    h2: ({node, ...props}) => (
                                        <h2 className="text-xl font-bold mt-6 mb-3" {...props} />
                                    ),
                                    h3: ({node, ...props}) => (
                                        <h3 className="text-lg font-semibold mt-4 mb-2" {...props} />
                                    ),
                                    p: ({node, ...props}) => (
                                        <p className="mb-4 leading-relaxed" {...props} />
                                    ),
                                    li: ({node, ...props}) => (
                                        <li className="mb-2 list-disc ml-6" {...props} />
                                    ),
                                    }}
                                >
                                    {analysisResult}
                                </ReactMarkdown>
                            </div>
                        </div>
                    )}

                    {error && (
                         <div className="mt-6 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg" role="alert">
                            <strong className="font-bold">Error:</strong>
                            <span className="block sm:inline ml-2">{error}</span>
                        </div>
                    )}
                </div>
            </div>
        </AuthenticatedLayout>
    );
}

