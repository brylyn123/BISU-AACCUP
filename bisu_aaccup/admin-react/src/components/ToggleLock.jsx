import React from 'react'

export default function ToggleLock({enabled, onToggle}){
  return (
    <div className="flex items-center gap-4">
      <div className="text-sm text-slate-700 font-medium">{enabled ? 'Documentation Locked' : 'Documentation Open'}</div>
      <button
        onClick={()=>onToggle(!enabled)}
        className={`w-14 h-8 flex items-center p-1 rounded-full transition-colors ${enabled ? 'bg-success' : 'bg-slate-300'}`}>
        <span className={`w-6 h-6 bg-white rounded-full shadow transform transition-transform ${enabled ? 'translate-x-6' : ''}`}></span>
      </button>
    </div>
  )
}
