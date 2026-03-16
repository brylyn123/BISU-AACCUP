import React, { useState } from 'react'
import Sidebar from './components/Sidebar'
import Navbar from './components/Navbar'
import StatCard from './components/StatCard'
import ManagementCard from './components/ManagementCard'
import ToggleLock from './components/ToggleLock'

export default function App(){
  const [locked, setLocked] = useState(false)

  return (
    <div className="min-h-screen bg-slate-50 text-slate-800">
      <div className="flex">
        <Sidebar />
        <div className="flex-1">
          <Navbar />

          <main className="p-8">
            <section className="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
              <StatCard title="Total Files Uploaded" value="1,254" />
              <StatCard title="Active Accreditations" value="12" />
              <StatCard title="Pending Reviews" value="18" />
            </section>

            <section className="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
              <ManagementCard 
                title="User Management"
                description="Manage Focal Persons and Deans, assign programs, and adjust roles."
                actionText="Open Users"
              />

              <ManagementCard 
                title="Program Settings"
                description="Edit college programs (CTE, CSM, CBMA, CFMS) and program-level settings."
                actionText="Edit Programs"
              />
            </section>

            <section className="bg-white rounded-xl border border-slate-200 shadow p-6">
              <div className="flex items-center justify-between">
                <div>
                  <h3 className="text-lg font-semibold text-slate-900">Global Lock Status</h3>
                  <p className="text-sm text-slate-600">Toggle to restrict focal persons from editing documentation.</p>
                </div>
                <div>
                  <ToggleLock enabled={locked} onToggle={(v)=>setLocked(v)} />
                </div>
              </div>
            </section>
          </main>
        </div>
      </div>
    </div>
  )
}
