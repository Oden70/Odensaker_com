import React, { useState } from "react";

interface Account {
  id: string;
  name: string;
  role: string;
}

const mockAccounts: Account[] = [
  { id: "1", name: "Anna Andersson", role: "Admin" },
  { id: "2", name: "Bertil Bengtsson", role: "User" },
  { id: "3", name: "Cecilia Carlsson", role: "User" },
  { id: "4", name: "David Dahl", role: "Manager" }
];

const roles = ["Alla", "Admin", "User", "Manager"];

export default function CompanyAccountsPage() {
  const [selectedRole, setSelectedRole] = useState<string>("Alla");

  const filteredAccounts = selectedRole === "Alla"
    ? mockAccounts
    : mockAccounts.filter(acc => acc.role === selectedRole);

  return (
    <div>
      <h1>Kopplade konton</h1>
      <label>
        Visa roll:
        <select value={selectedRole} onChange={e => setSelectedRole(e.target.value)}>
          {roles.map(role => (
            <option key={role} value={role}>{role}</option>
          ))}
        </select>
      </label>
      <ul>
        {filteredAccounts.map(acc => (
          <li key={acc.id}>
            {acc.name} ({acc.role})
          </li>
        ))}
      </ul>
    </div>
  );
}
