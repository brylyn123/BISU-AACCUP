import React from 'react'
import { Users, Settings } from 'lucide-react'

export default function ManagementCard({title, description, actionText}){
  const Icon = title.toLowerCase().includes('user') ? Users : Settings
  return (
    <div className="bg-white rounded-xl border border-slate-200 shadow-sm p-6 flex flex-col justify-between">
      <div>
        <div className="flex items-center gap-3">
          <Icon className="w-6 h-6 text-slate-700" />
          <h3 className="text-lg font-semibold text-slate-900">{title}</h3>
        </div>
        <p className="text-sm text-slate-600 mt-3">{description}</p>
      </div>
      <div className="mt-6">
        <a href="#" className="inline-flex items-center px-4 py-2 rounded-md bg-bisu text-white">{actionText}</a>
      </div>
    </div>
  )
}
