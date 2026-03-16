import React from 'react'

export default function StatCard({title, value}){
  return (
    <div className="bg-white rounded-xl border border-slate-200 shadow-sm p-6">
      <div className="text-sm text-slate-500">{title}</div>
      <div className="mt-3 text-2xl font-semibold text-slate-900">{value}</div>
    </div>
  )
}
