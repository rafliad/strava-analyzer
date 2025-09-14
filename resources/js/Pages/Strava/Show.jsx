import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link } from '@inertiajs/react';
import { useState, useMemo } from 'react';
import {
    AreaChart, Area, Line, XAxis, YAxis, CartesianGrid, Tooltip, Legend, ResponsiveContainer
} from 'recharts';
import axios from 'axios';
import toast from 'react-hot-toast';
import ReactMarkdown from 'react-markdown';


// Komponen Toggle Switch yang bisa digunakan kembali
const ToggleSwitch = ({ label, isEnabled, onToggle }) => (
    <div className="flex items-center space-x-2">
        <label htmlFor={label} className="text-sm font-medium text-gray-700">{label}</label>
        <button
            id={label}
            onClick={onToggle}
            className={`relative inline-flex h-6 w-11 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 ${isEnabled ? 'bg-indigo-600' : 'bg-gray-200'}`}
            type="button"
        >
            <span
                aria-hidden="true"
                className={`inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out ${isEnabled ? 'translate-x-5' : 'translate-x-0'}`}
            />
        </button>
    </div>
);


export default function Show({ auth, activity }) {
    const [visibleData, setVisibleData] = useState({
        pace: true,
        heartrate: false,
        power: false,
    });

    // NOTE: State baru untuk analisis aktivitas tunggal
    const [singleAnalysisResult, setSingleAnalysisResult] = useState(null);
    const [isAnalyzing, setIsAnalyzing] = useState(false);

    const handleToggle = (key) => {
        setVisibleData(prev => ({ ...prev, [key]: !prev[key] }));
    };

    // NOTE: Fungsi baru untuk menangani permintaan analisis
    const handleSingleAnalysis = async () => {
        setIsAnalyzing(true);
        setSingleAnalysisResult(null);
        toast.loading('Analyzing your activity with Gemini AI...');

        try {
            const response = await axios.post(route('analysis.performSingle', { activity: activity.id }));
            setSingleAnalysisResult(response.data.analysis);
            toast.dismiss(); // Hapus toast loading
            toast.success('Analysis complete!');
        } catch (error) {
            console.error('Error performing single analysis:', error);
            toast.dismiss();
            toast.error('Failed to analyze this activity.');
        } finally {
            setIsAnalyzing(false);
        }
    };


    const formatTime = (seconds) => {
        if (seconds === null || seconds === undefined) return '00h 00m 00s';
        const h = Math.floor(seconds / 3600).toString().padStart(2, '0');
        const m = Math.floor((seconds % 3600) / 60).toString().padStart(2, '0');
        const s = (seconds % 60).toString().padStart(2, '0');
        return `${h}h ${m}m ${s}s`;
    };

    const formatPace = (decimalMinutes) => {
        if (decimalMinutes === null || isNaN(decimalMinutes)) return 'N/A';
        const minutes = Math.floor(decimalMinutes);
        const seconds = Math.round((decimalMinutes - minutes) * 60);
        return `${minutes}:${seconds.toString().padStart(2, '0')}`;
    };

    const stats = [
        { name: 'Jarak', value: `${(activity.distance / 1000).toFixed(2)} km` },
        { name: 'Waktu Bergerak', value: formatTime(activity.moving_time) },
        { name: 'Waktu Total', value: formatTime(activity.elapsed_time) },
        { name: 'Total Tanjakan', value: `${activity.total_elevation_gain} m` },
        { name: 'Tipe', value: activity.type },
        { name: 'Tanggal', value: new Date(activity.start_date).toLocaleDateString('id-ID', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' }) },
    ];

    const streams = useMemo(() => {
        if (!activity.streams) return [];
        const parsedStreams = JSON.parse(activity.streams);
        if (!Array.isArray(parsedStreams) || parsedStreams.length === 0) return [];
        const PACE_THRESHOLD = 20;


        return parsedStreams.map((stream, index, array) => {
            let pace = 0;
            if (index > 0) {
                const prevStream = array[index - 1];
                const deltaDistance = stream.distance - prevStream.distance;
                const deltaTime = stream.time - prevStream.time;
                if (deltaDistance > 0 && deltaTime > 0) {
                    const secondsPerKm = (deltaTime / deltaDistance) * 1000;
                    pace = secondsPerKm / 60;
                }
            }
            const finalPace = (pace > 0 && pace < PACE_THRESHOLD) ? parseFloat(pace.toFixed(2)) : null;

            return {
                ...stream,
                distance_km: parseFloat((stream.distance / 1000).toFixed(2)),
                pace: finalPace,
            };
        });
    }, [activity.streams]);

    const paceDomain = useMemo(() => {
        if (streams.length === 0) return [3, 10];
        
        const paceValues = streams.map(s => s.pace).filter(p => p !== null);

        if (paceValues.length === 0) return [3, 10];

        const minPace = Math.min(...paceValues);
        const maxPace = Math.max(...paceValues);

        const lowerBound = Math.floor(minPace * 0.9);
        const upperBound = Math.ceil(maxPace * 1.1);

        return [lowerBound, upperBound];
    }, [streams]);

    const customTooltipFormatter = (value, name) => {
        if (value === null || value === undefined) return null;
        switch (name) {
            case 'Pace':
                return [`${formatPace(value)} /km`, name];
            case 'Detak Jantung':
                return [`${value} bpm`, name];
            case 'Power':
                return [`${value} W`, name];
            case 'Elevasi':
                return [`${Math.round(value)} m`, name];
            default:
                return [value, name];
        }
    };


    return (
        <AuthenticatedLayout
            user={auth.user}
            header={
                <div className="flex items-center justify-between">
                    <h2 className="font-semibold text-xl text-gray-800 leading-tight">Detail Aktivitas</h2>
                    <Link href={route('activities.index')} className="text-sm text-indigo-600 hover:text-indigo-900">&larr; Kembali ke Daftar</Link>
                </div>
            }
        >
            <Head title={activity.name} />

            <div className="py-12">
                <div className="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
                    <div className="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                        <div className="p-6 border-b border-gray-200">
                            <h3 className="text-2xl font-bold text-gray-900">{activity.name}</h3>
                        </div>
                        <div className="bg-gray-50 px-6 py-5">
                            <dl className="grid grid-cols-1 gap-x-4 gap-y-8 sm:grid-cols-2">
                                {stats.map((stat) => (
                                    <div key={stat.name} className="sm:col-span-1">
                                        <dt className="text-sm font-medium text-gray-500">{stat.name}</dt>
                                        <dd className="mt-1 text-lg font-semibold text-gray-900">{stat.value}</dd>
                                    </div>
                                ))}
                            </dl>
                        </div>
                    </div>

                    {/* NOTE: Bagian baru untuk tombol dan hasil analisis */}
                    <div className="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                        <div className="flex justify-center">
                             <button
                                 onClick={handleSingleAnalysis}
                                 disabled={isAnalyzing}
                                 className="inline-flex justify-center py-2 px-6 border border-transparent shadow-sm text-base font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 disabled:opacity-50"
                             >
                                 {isAnalyzing ? 'Analyzing...' : 'Analyze This Activity'}
                             </button>
                         </div>

                        {singleAnalysisResult && (
                             <div className="mt-6 prose prose-lg max-w-none">
                                 <ReactMarkdown
                                    components={{
                                        h2: ({node, ...props}) => <h2 className="text-xl font-bold mt-6 mb-3" {...props} />,
                                        h3: ({node, ...props}) => <h3 className="text-lg font-semibold mt-4 mb-2" {...props} />,
                                        p: ({node, ...props}) => <p className="mb-4 leading-relaxed" {...props} />,
                                        li: ({node, ...props}) => <li className="mb-2 list-disc ml-6" {...props} />,
                                    }}
                                 >
                                    {singleAnalysisResult}
                                 </ReactMarkdown>
                             </div>
                         )}
                    </div>

                    {streams.length > 0 && (
                        <div className="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                            <h3 className="text-lg font-semibold mb-4 text-center">Grafik Performa</h3>
                            <div className="flex justify-center space-x-6 mb-4">
                                <ToggleSwitch label="Pace" isEnabled={visibleData.pace} onToggle={() => handleToggle('pace')} />
                                <ToggleSwitch label="Detak Jantung" isEnabled={visibleData.heartrate} onToggle={() => handleToggle('heartrate')} />
                                <ToggleSwitch label="Power" isEnabled={visibleData.power} onToggle={() => handleToggle('power')} />
                            </div>
                            <div style={{ height: '400px' }}>
                                <ResponsiveContainer width="100%" height="100%">
                                    <AreaChart data={streams} margin={{ top: 5, right: 30, left: 20, bottom: 20 }}>
                                        <CartesianGrid strokeDasharray="3 3" />
                                        <XAxis dataKey="distance_km" type="number" domain={['dataMin', 'dataMax']} label={{ value: 'Jarak (km)', position: 'insideBottom', offset: -10 }} />
                                        <YAxis yAxisId="altitude" orientation="left" label={{ value: 'Elevasi (m)', angle: -90, position: 'insideLeft' }} stroke="#8884d8" domain={['dataMin - 20', 'dataMax + 20']} />
                                        {visibleData.pace && <YAxis yAxisId="pace" orientation="right" label={{ value: 'Pace (min/km)', angle: -90, position: 'insideRight' }} stroke="#ff7300" reversed={true} domain={paceDomain} tickFormatter={formatPace} />}
                                        <Tooltip labelFormatter={(label) => `Jarak: ${label.toFixed(2)} km`} formatter={customTooltipFormatter} />
                                        <Legend verticalAlign="top" wrapperStyle={{ paddingBottom: "10px" }} />
                                        <Area yAxisId="altitude" type="monotone" dataKey="altitude" stroke="#cccccc" fill="#cccccc" dot={false} name="Elevasi" />
                                        {visibleData.pace && <Line yAxisId="pace" type="monotone" dataKey="pace" stroke="#ff7300" dot={false} name="Pace" />}
                                        {visibleData.heartrate && <Line yAxisId="hr" type="monotone" dataKey="heartrate" stroke="#82ca9d" dot={false} name="Detak Jantung" />}
                                        {visibleData.power && <Line yAxisId="power" type="monotone" dataKey="watts" stroke="#8884d8" dot={false} name="Power" />}
                                    </AreaChart>
                                </ResponsiveContainer>
                            </div>
                        </div>
                    )}
                </div>
            </div>
        </AuthenticatedLayout>
    );
}

