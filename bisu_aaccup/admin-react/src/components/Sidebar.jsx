import React from 'react'
import { Users, Layers, CalendarCheck, Settings, Lock } from 'lucide-react'

export default function Sidebar(){
  const colleges = ['CTE','CSM','CBMA','CFMS']

  return (
    <aside className="w-72 min-h-screen bg-gradient-to-b from-slate-900 to-slate-800 text-white sticky top-0 p-6">
      <div className="mb-8">
        <div className="text-2xl font-bold">Director Gina</div>
        <div className="text-sm text-slate-300 mt-1">System Admin</div>
      </div>

      <nav className="space-y-2 text-sm">
        <a className="flex items-center gap-3 px-3 py-2 rounded hover:bg-slate-800" href="#">
          <CalendarCheck className="w-5 h-5 text-slate-300" /> <span>Dashboard Overview</span>
        </a>

        <div className="mt-2">
          <div className="text-xs uppercase text-slate-400 px-3 mb-1">User Management</div>
          <a className="flex items-center gap-3 px-3 py-2 rounded hover:bg-slate-800" href="users.php">
            <Users className="w-5 h-5 text-slate-300" /> <span>Focal Persons / Deans</span>
          </a>
        </div>

        <div className="mt-4">
          <div className="text-xs uppercase text-slate-400 px-3 mb-1">College Management</div>
          {colleges.map(c=> (
            <a key={c} className="flex items-center gap-3 px-3 py-2 rounded hover:bg-slate-800" href="#">
              <Layers className="w-5 h-5 text-slate-300" /> <span>{c}</span>
            </a>
          ))}
        </div>

        <div className="mt-4">
          <div className="text-xs uppercase text-slate-400 px-3 mb-1">Accreditation</div>
          <a className="flex items-center gap-3 px-3 py-2 rounded hover:bg-slate-800" href="#">
            <CalendarCheck className="w-5 h-5 text-slate-300" /> <span>Level 2 / 3 Cycles</span>
          </a>
        </div>

        <div className="mt-4">
          <div className="text-xs uppercase text-slate-400 px-3 mb-1">Global</div>
          <a className="flex items-center gap-3 px-3 py-2 rounded hover:bg-slate-800" href="#">
            <Lock className="w-5 h-5 text-slate-300" /> <span>Global Lock Status</span>
          </a>
          <a className="flex items-center gap-3 px-3 py-2 rounded text-red-300 hover:bg-slate-800 mt-3" href="logout.php">Logout</a>
        </div>
      </nav>
    </aside>
  )
}
