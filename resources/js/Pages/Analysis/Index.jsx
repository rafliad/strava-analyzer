import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head } from '@inertiajs/react';
import axios from 'axios';
import { useState } from 'react';
import ReactMarkdown from 'react-markdown';

export default function Index({ auth }) {
    const [isLoading, setIsLoading] = useState(false);
    const [analysisResult, setAnalysisResult] = useState('');
    const [error, setError] = useState('');

    const handleAnalysisClick = async () => {
        setIsLoading(true);
        setError('');
        setAnalysisResult('');

        try {
            const response = await axios.post(route('analysis.perform'));
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
                    <div className="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6 text-center">
                        <div className="mb-4">
                            <h3 className="text-lg font-medium text-gray-900">Get Your Performance Review</h3>
                            <p className="mt-1 text-sm text-gray-600">
                                Click the button below to send your recent activity data to our AI coach for analysis and feedback.
                            </p>
                        </div>
                        <button
                            onClick={handleAnalysisClick}
                            disabled={isLoading}
                            className="px-6 py-3 bg-indigo-600 text-white font-semibold rounded-lg shadow-md hover:bg-indigo-800 disabled:bg-indigo-300 disabled:cursor-not-allowed transition-colors"
                        >
                            {isLoading ? 'Analyzing...' : 'Analyze My Recent Performance'}
                        </button>
                    </div>

                    {/* Bagian untuk menampilkan hasil */}
                    {analysisResult && (
                        <div className="mt-6 bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                            <h3 className="text-lg font-medium text-gray-900 mb-4">AI Coach Feedback:</h3>
                            {/* Menggunakan 'whitespace-pre-wrap' untuk menghormati format teks dari AI */}
                            <div className="prose max-w-none text-gray-700 whitespace-pre-wrap">
                                <ReactMarkdown>{analysisResult}</ReactMarkdown>
                            </div>
                        </div>
                    )}

                    {/* Bagian untuk menampilkan error */}
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
