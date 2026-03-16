import React from 'react'
import { User } from 'lucide-react'

export default function Navbar(){
  return (
    <header className="bg-white border-b border-slate-200 p-4 flex items-center justify-between">
      <div>
        {/* breadcrumb / title */}
      </div>
      <div className="flex items-center gap-4">
        <div className="flex items-center gap-3">
          <div className="w-10 h-10 rounded-full bg-slate-100 flex items-center justify-center">
            <User className="w-5 h-5 text-slate-600" />
          </div>
          <div className="text-sm">
            <div className="font-semibold text-slate-900">Director Gina</div>
            <div className="text-xs text-slate-500">System Admin</div>
          </div>
        </div>
        <form action="logout.php" method="post">
          <button type="submit" className="px-3 py-2 rounded bg-bisu text-white text-sm">Logout</button>
        </form>
      </div>
    </header>
  )
}
